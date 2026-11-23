<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Country;
use App\Models\Region;
use App\Models\Route;
use App\Support\Geo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as PaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Satu pintu pencarian bandara untuk web, API, dan chat.
 *
 * Mode live menjalankan pencarian katalog langsung di Fuseki melalui SELECT SPARQL.
 * Mode lokal/pengujian tetap memakai Eloquent agar fixture dan pengembangan offline
 * tidak bergantung pada endpoint Fuseki.
 */
class AirportSearch
{
    public const PER_PAGE = 15;

    private const KATA_UMUM = [
        'bandara', 'bandar', 'udara', 'airport', 'kota', 'kabupaten', 'provinsi',
        'wilayah', 'di', 'ke', 'dari', 'the', 'international', 'internasional',
        'domestik', 'domestic',
    ];

    /**
     * Pulau/gugus pulau -> provinsi di dalamnya (nama sudah dinormalisasi).
     * Query "sumatera" hanya menampilkan bandara di provinsi-provinsi ini.
     */
    private const PULAU = [
        'sumatera' => [
            'aceh', 'sumatera utara', 'sumatera barat', 'riau', 'kepulauan riau', 'jambi',
            'sumatera selatan', 'bengkulu', 'lampung', 'kepulauan bangka belitung',
        ],
        'jawa' => ['banten', 'jakarta', 'jawa barat', 'jawa tengah', 'jawa timur', 'yogyakarta'],
        'kalimantan' => [
            'kalimantan barat', 'kalimantan tengah', 'kalimantan selatan', 'kalimantan timur', 'kalimantan utara',
        ],
        'sulawesi' => [
            'sulawesi utara', 'sulawesi tengah', 'sulawesi selatan', 'sulawesi tenggara', 'sulawesi barat', 'gorontalo',
        ],
        'papua' => [
            'papua', 'papua barat', 'papua barat daya', 'papua pegunungan', 'papua selatan', 'papua tengah',
        ],
        'maluku' => ['maluku', 'maluku utara'],
        'nusa tenggara' => ['nusa tenggara barat', 'nusa tenggara timur'],
    ];

    /** Nama Inggris -> nama di data. */
    private const ALIAS_WILAYAH = [
        // Singkatan provinsi Indonesia yang umum dipakai saat pencarian.
        'nad' => 'aceh',
        'sumut' => 'sumatera utara', 'sumbar' => 'sumatera barat', 'sumsel' => 'sumatera selatan',
        'kepri' => 'kepulauan riau', 'babel' => 'kepulauan bangka belitung',
        'dki' => 'jakarta', 'dki jakarta' => 'jakarta',
        'jabar' => 'jawa barat', 'jateng' => 'jawa tengah', 'jatim' => 'jawa timur',
        'diy' => 'yogyakarta', 'jogja' => 'yogyakarta', 'jogjakarta' => 'yogyakarta',
        'ntb' => 'nusa tenggara barat', 'ntt' => 'nusa tenggara timur',
        'kalbar' => 'kalimantan barat', 'kalteng' => 'kalimantan tengah',
        'kalsel' => 'kalimantan selatan', 'kaltim' => 'kalimantan timur', 'kaltara' => 'kalimantan utara',
        'sulut' => 'sulawesi utara', 'sulteng' => 'sulawesi tengah',
        'sulsel' => 'sulawesi selatan', 'sultra' => 'sulawesi tenggara', 'sulbar' => 'sulawesi barat',
        'malut' => 'maluku utara', 'papbar' => 'papua barat', 'pabar' => 'papua barat',
        'pabardaya' => 'papua barat daya', 'papteng' => 'papua tengah',
        'papeg' => 'papua pegunungan', 'papsel' => 'papua selatan',
        // Nama Inggris -> nama di data.
        'north sumatera' => 'sumatera utara',
        'west sumatera' => 'sumatera barat',
        'south sumatera' => 'sumatera selatan',
        'west java' => 'jawa barat',
        'central java' => 'jawa tengah',
        'east java' => 'jawa timur',
        'north sulawesi' => 'sulawesi utara',
        'south sulawesi' => 'sulawesi selatan',
        'west kalimantan' => 'kalimantan barat',
        'east kalimantan' => 'kalimantan timur',
        'north kalimantan' => 'kalimantan utara',
        // Wilayah internasional: Amerika Serikat, Kanada, dan Australia.
        'ca' => 'california', 'calif' => 'california', 'ny' => 'new york',
        'tx' => 'texas', 'fl' => 'florida', 'il' => 'illinois', 'dc' => 'district of columbia',
        'bc' => 'british columbia', 'ab' => 'alberta', 'on' => 'ontario', 'qc' => 'quebec',
        'nsw' => 'new south wales', 'vic' => 'victoria', 'qld' => 'queensland',
        'sa australia' => 'south australia', 'wa australia' => 'western australia',
    ];

    /**
     * Nama lain / singkatan / bahasa Inggris -> label negara di data (sudah dinormalisasi).
     * Dipakai agar query "indo", "philippines", "uae" dll. dikenali sebagai negara.
     */
    private const ALIAS_NEGARA = [
        'indo' => 'indonesia', 'indonesian' => 'indonesia', 'republik indonesia' => 'indonesia',
        'philippines' => 'filipina', 'philippine' => 'filipina', 'pilipina' => 'filipina',
        'cambodia' => 'kamboja', 'singapore' => 'singapura', 'burma' => 'myanmar',
        'east timor' => 'timor leste', 'timor leste' => 'timor leste',
        'brunei darussalam' => 'brunei', 'viet nam' => 'vietnam', 'hongkong' => 'hong kong',
        'tiongkok' => 'china', 'cina' => 'china', 'jepang' => 'japan', 'rusia' => 'russia',
        'jerman' => 'germany', 'prancis' => 'france', 'belanda' => 'netherlands',
        'inggris' => 'united kingdom', 'uk' => 'united kingdom', 'amerika serikat' => 'united states',
        'usa' => 'united states', 'uae' => 'united arab emirates', 'png' => 'papua new guinea',
        'korea selatan' => 'south korea', 'korea utara' => 'north korea',
        'id' => 'indonesia', 'ph' => 'filipina', 'phl' => 'filipina',
        'sg' => 'singapura', 'my' => 'malaysia', 'th' => 'thailand',
        'vn' => 'vietnam', 'kh' => 'kamboja', 'la' => 'laos',
        'bn' => 'brunei', 'mm' => 'myanmar', 'tl' => 'timor leste',
        'cn' => 'china', 'chn' => 'china', 'jp' => 'japan', 'kr' => 'south korea',
        'kp' => 'north korea', 'us' => 'united states', 'gb' => 'united kingdom',
        'gbr' => 'united kingdom', 'ae' => 'united arab emirates', 'nz' => 'new zealand',
        'za' => 'south africa', 'de' => 'germany', 'fr' => 'france', 'nl' => 'netherlands',
        'ru' => 'russia', 'pg' => 'papua new guinea',
        'arab saudi' => 'saudi arabia', 'selandia baru' => 'new zealand', 'afrika selatan' => 'south africa',
    ];

    /**
     * @param  array{
     *     q?: string|null,
     *     negara?: string|null,
     *     kategori?: string|null,
     *     asean?: string|null,
     *     rute?: string|null,
     *     urut?: string|null,
     *     near?: string|null,
     *     radius?: int|string|null,
     *     page?: int|string|null,
     *     per_page?: int|string|null
     * }  $opsi
     */
    public function cari(array $opsi = []): PaginatorContract
    {
        $q = trim((string) ($opsi['q'] ?? ''));
        // Normalisasi awalan administratif berlaku untuk semua kota/kabupaten,
        // bukan hanya Medan: "Kota Surabaya" -> "Surabaya\".
        $q = preg_replace('/^(?:kota|kabupaten|kab\.?)\s+/iu', '', $q) ?? $q;
        $aliasKota = $this->normalisasi($q);
        // KNO secara administratif berada di Deli Serdang, tetapi menjadi bandara
        // utama yang melayani Medan. Kota lain dicari dari label wilayah RDF/database.
        if (in_array($aliasKota, ['medan', 'medan city'], true)) {
            $q = 'kualanamu';
        }
        $page = max(1, (int) ($opsi['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($opsi['per_page'] ?? self::PER_PAGE)));
        $near = Geo::parseKoordinat($opsi['near'] ?? null);
        $radius = max(1, min(2000, (int) ($opsi['radius'] ?? 100)));
        $urut = (string) ($opsi['urut'] ?? 'relevan');

        // Dalam mode live, katalog dicari langsung di Fuseki melalui SELECT SPARQL.
        // Jalur Eloquent dipertahankan hanya untuk mode pengujian/lokal.
        if (config('sparql.live')) {
            return app(AirportSparqlSearch::class)->search($opsi);
        }

        $query = $this->queryDasar($opsi);

        if ($q === '' && $near === null) {
            $paginator = $query
                ->when($urut === 'nama', fn (Builder $builder) => $builder->orderBy('label'))
                ->when($urut === 'penumpang', fn (Builder $builder) => $builder
                    ->orderByDesc('annual_passengers')
                    ->orderBy('label'))
                ->when(! in_array($urut, ['nama', 'penumpang'], true), fn (Builder $builder) => $builder->orderBy('label'))
                ->paginate($perPage, ['*'], 'page', $page)
                ->withQueryString();

            return $paginator->through(fn (Airport $airport): array => $this->baris($airport));
        }

        if ($near === null && $this->normalisasi($q) === '') {
            // query hanya tanda baca ("%", "--"): tidak ada yang bisa dicocokkan
            return new LengthAwarePaginator([], 0, $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);
        }

        $token = $this->token($q);
        $kunci = 'cari.'.md5(json_encode([
            'q' => mb_strtolower($q),
            'negara' => $opsi['negara'] ?? null,
            'kategori' => $opsi['kategori'] ?? null,
            'asean' => $opsi['asean'] ?? null,
            'rute' => $opsi['rute'] ?? null,
            'urut' => $urut,
            'near' => $near,
            'radius' => $radius,
            'token' => $token,
            'v' => 4,
        ]));

        $baris = Cache::remember($kunci, now()->addSeconds(90), function () use ($query, $near, $radius, $q, $token, $urut): array {
            $kandidat = $query->get();

            // Query = nama negara ("indo", "indonesia", "philippines"): hanya bandara negara itu.
            // Kata lain di query ("indonesia medan") tetap harus cocok di dalam negara tersebut.
            $negara = $this->lingkupNegara($q);

            if ($negara !== null) {
                $kandidat = $kandidat
                    ->filter(fn (Airport $airport): bool => (int) $airport->country_id === $negara['id'])
                    ->values();
                $q = $negara['sisa'];
                $token = $q === '' ? [] : $this->token($q);
            }

            $lingkup = $q === '' ? null : $this->lingkupWilayah($q);

            // Tahap 1: semua kata harus cocok (AND) dan tanpa toleransi typo, supaya
            // "sumatera utara" tidak membawa "Zamboanga Utara" hanya karena kata "utara",
            // dan "bali" tidak membawa "Bole" atau "Bao'an" lewat kemiripan huruf.
            $hasil = $this->kumpulkan($kandidat, $q, $token, $near, $radius, $lingkup, true, false);

            // Tahap 2: kalau kosong, anggap salah ketik: nama provinsi/pulau yang mirip
            // ("sumatera utra"), lalu kata yang mirip ("kualanamo").
            if ($hasil === [] && $lingkup === null) {
                $lingkupMirip = $this->lingkupWilayah($q, true);

                $hasil = $lingkupMirip !== null
                    ? $this->kumpulkan($kandidat, $q, $token, $near, $radius, $lingkupMirip, true, false)
                    : $this->kumpulkan($kandidat, $q, $token, $near, $radius, null, true, true);
            }

            // Tahap 3: masih kosong dan query lebih dari satu kata -> cukup salah satu kata cocok.
            if ($hasil === [] && $lingkup === null && ($lingkupMirip ?? null) === null && count($token) > 1) {
                $hasil = $this->kumpulkan($kandidat, $q, $token, $near, $radius, null, false, true);
            }

            // Tahap 4: masih kosong -> tanya langsung ke Fuseki dengan FILTER REGEX (cocok sebagian).
            if ($hasil === [] && $near === null && $lingkup === null && $q !== '') {
                $hasil = $this->cariRegexFuseki($query, $q);
            }

            return $this->urutkan($hasil, $near !== null, $urut);
        });

        return new LengthAwarePaginator(
            array_slice($baris, ($page - 1) * $perPage, $perPage),
            count($baris),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * @param  iterable<int, Airport>  $kandidat
     * @param  list<string>  $token
     * @param  list<int>|null  $lingkup  id wilayah (beserta turunannya) kalau query berupa nama provinsi/pulau
     * @param  array{lat: float, lon: float}|null  $near
     * @return list<array<string, mixed>>
     */
    private function kumpulkan(iterable $kandidat, string $q, array $token, ?array $near, int $radius, ?array $lingkup, bool $semuaKata, bool $toleransiTypo): array
    {
        $hasil = [];
        $diLingkup = $lingkup === null ? [] : array_flip($lingkup);

        foreach ($kandidat as $airport) {
            $jarak = null;

            if ($near !== null) {
                if ($airport->latitude === null || $airport->longitude === null) {
                    continue;
                }

                $jarak = Geo::jarakKm($near['lat'], $near['lon'], $airport->latitude, $airport->longitude);

                if ($jarak > $radius) {
                    continue;
                }
            }

            if ($lingkup !== null) {
                // Query = nama provinsi/pulau: hanya bandara di wilayah itu (atau yang
                // namanya/kodenya memang persis dicari, mis. alias "Jakarta" untuk CGK).
                $skor = isset($diLingkup[$airport->region_id]) ? 1500 : $this->skorIdentitas($airport, $q);
            } else {
                $skor = $q === '' ? 1 : $this->skor($airport, $q, $token, $semuaKata, $toleransiTypo);
            }

            if ($skor <= 0) {
                continue;
            }

            $hasil[] = $this->baris($airport, $skor, $jarak);
        }

        return $hasil;
    }

    /**
     * Pencarian langsung ke Fuseki: SparqlService::search() memakai
     * FILTER(REGEX(LCASE(STR(?teks)), "kata", "i")) pada nama, kode, wilayah, dan negara.
     * Hanya jalan di mode live dan memakai filter yang sama (negara, kategori, dst.).
     * Kalau Fuseki tidak bisa dihubungi, hasilnya dianggap kosong.
     *
     * @return list<array<string, mixed>>
     */
    private function cariRegexFuseki(Builder $query, string $q): array
    {
        if (! config('sparql.live')) {
            return [];
        }

        try {
            $iata = array_values(array_filter(array_column(app(SparqlService::class)->search($q, 50), 'iata')));
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        if ($iata === []) {
            return [];
        }

        return (clone $query)
            ->whereIn('iata_code', $iata)
            ->get()
            ->map(fn (Airport $airport): array => $this->baris($airport, 100))
            ->all();
    }

    /**
     * Kecocokan lewat identitas bandara saja: kode, alias, atau nama. Tanpa wilayah/negara/fuzzy.
     */
    private function skorIdentitas(Airport $airport, string $q): int
    {
        $qNormal = $this->normalisasi($q);

        if ($qNormal === '') {
            return 0;
        }
        $label = $this->normalisasi((string) $airport->label);

        if ($qNormal === $this->normalisasi((string) $airport->iata_code) || $qNormal === $this->normalisasi((string) $airport->icao_code)) {
            return 1200;
        }

        if ($airport->aliases->contains(fn ($alias): bool => $this->normalisasi((string) $alias->alias) === $qNormal)) {
            return 1100;
        }

        if ($label === $qNormal) {
            return 1000;
        }

        return str_contains(' '.$label.' ', ' '.$qNormal.' ') ? 650 : 0;
    }

    /**
     * Kalau query memuat nama negara ("indonesia", "indo", "philippines", "bandara malaysia"),
     * kembalikan id negara itu dan sisa query tanpa kata negara/kata umum.
     * Awalan nama negara (minimal 4 huruf, mis. "indo", "mala") hanya dikenali kalau itu
     * satu-satunya isi query dan hanya cocok ke satu negara, supaya "north sumatra" tidak
     * dianggap Korea Utara dan "papua" tetap berarti provinsi Papua.
     *
     * @return array{id: int, sisa: string}|null
     */
    private function lingkupNegara(string $q): ?array
    {
        $inti = array_values(array_filter(
            $this->kata($q),
            fn (string $kata): bool => ! in_array($kata, self::KATA_UMUM, true)
        ));

        if ($inti === []) {
            return null;
        }

        $label = $this->indeksNegara();

        // Nama persis atau alias, frasa terpanjang dulu (maks. 3 kata: "papua new guinea").
        for ($panjang = min(3, count($inti)); $panjang >= 1; $panjang--) {
            for ($mulai = 0; $mulai + $panjang <= count($inti); $mulai++) {
                $frasa = implode(' ', array_slice($inti, $mulai, $panjang));
                $frasa = self::ALIAS_NEGARA[$frasa] ?? $frasa;
                $id = array_search($frasa, $label, true);

                if ($id !== false) {
                    $sisa = array_merge(array_slice($inti, 0, $mulai), array_slice($inti, $mulai + $panjang));

                    return ['id' => (int) $id, 'sisa' => implode(' ', $sisa)];
                }
            }
        }

        // Awalan nama negara saat mengetik ("indo", "indon"), hanya kalau tidak ambigu.
        $semua = implode(' ', $inti);

        if (mb_strlen($semua) >= 4 && $this->lingkupWilayah($semua) === null) {
            $cocok = array_keys(array_filter($label, fn (string $nama): bool => str_starts_with($nama, $semua)));

            // label negara di data berbahasa Indonesia, jadi awalan bahasa Inggris juga dikenali
            // lewat alias ("phili" -> Filipina, "cambo" -> Kamboja, "burm" -> Myanmar)
            foreach (self::ALIAS_NEGARA as $alias => $namaLabel) {
                if (str_starts_with($alias, $semua) && ($id = array_search($namaLabel, $label, true)) !== false) {
                    $cocok[] = $id;
                }
            }

            $cocok = array_values(array_unique($cocok));

            if (count($cocok) === 1) {
                return ['id' => (int) $cocok[0], 'sisa' => ''];
            }
        }

        return null;
    }

    /**
     * @return array<int, string> id negara => label ternormalisasi
     */
    private function indeksNegara(): array
    {
        return Cache::remember('negara.indeks.v1', now()->addSeconds(90), function (): array {
            $label = [];

            foreach (Country::query()->get(['id', 'label']) as $negara) {
                $label[(int) $negara->id] = $this->normalisasi((string) $negara->label);
            }

            return $label;
        });
    }

    /**
     * Kalau seluruh query adalah nama provinsi atau pulau ("sumatera utara", "sumatera",
     * "north sumatra"), kembalikan id wilayah itu beserta semua turunannya. Selain itu null.
     *
     * @return list<int>|null
     */
    private function lingkupWilayah(string $q, bool $mirip = false): ?array
    {
        $kunci = implode(' ', $this->token($q));

        if ($kunci === '') {
            return null;
        }

        $kunci = str_replace('sumatra', 'sumatera', $kunci);
        $kunci = self::ALIAS_WILAYAH[$kunci] ?? $kunci;
        $pulau = preg_replace('/^(pulau|island)\s+|\s+island$/u', '', $kunci) ?? $kunci;

        $indeks = $this->indeksWilayah();
        $cocok = [];

        // Awalan juga diterima ("sumat" saat mengetik di kotak pencarian), minimal 5 huruf.
        if (! isset(self::PULAU[$pulau]) && mb_strlen($pulau) >= 5) {
            foreach (array_keys(self::PULAU) as $namaPulau) {
                if (str_starts_with($namaPulau, $pulau)) {
                    $pulau = $namaPulau;
                    break;
                }
            }
        }

        if (isset(self::PULAU[$pulau])) {
            $nama = array_flip(self::PULAU[$pulau]);

            foreach ($indeks['label'] as $id => $label) {
                if (isset($nama[$label]) && $this->tingkatProvinsi($id, $indeks)) {
                    $cocok[] = $id;
                }
            }
        } else {
            foreach ($indeks['label'] as $id => $label) {
                if ($label === $kunci && $this->tingkatProvinsi($id, $indeks)) {
                    $cocok[] = $id;
                }
            }
        }

        // Salah ketik nama provinsi/pulau ("sumatera utra"): ambil yang paling mirip.
        if ($cocok === [] && $mirip && mb_strlen($kunci) >= 5) {
            $batas = mb_strlen($kunci) >= 9 ? 2 : 1;

            foreach (array_keys(self::PULAU) as $namaPulau) {
                if (levenshtein($pulau, $namaPulau) <= $batas) {
                    return $this->lingkupWilayah($namaPulau);
                }
            }

            $terbaik = $batas;

            foreach ($indeks['label'] as $id => $label) {
                $jarak = levenshtein($kunci, $label);

                if ($jarak > $terbaik || ! $this->tingkatProvinsi($id, $indeks)) {
                    continue;
                }

                if ($jarak < $terbaik) {
                    $cocok = [];
                    $terbaik = $jarak;
                }

                $cocok[] = $id;
            }
        }

        if ($cocok === []) {
            return null;
        }

        $anak = [];
        foreach ($indeks['induk'] as $id => $induk) {
            if ($induk !== null) {
                $anak[$induk][] = $id;
            }
        }

        $semua = $cocok;
        for ($antre = $cocok; $antre !== [];) {
            $id = array_pop($antre);

            foreach ($anak[$id] ?? [] as $turunan) {
                if (! in_array($turunan, $semua, true)) {
                    $semua[] = $turunan;
                    $antre[] = $turunan;
                }
            }
        }

        return $semua;
    }

    /**
     * Provinsi = wilayah tepat di bawah akar negara, atau akar itu sendiri
     * kalau datanya tidak punya akar negara (mis. provinsi Thailand). Nama negara
     * sendiri tidak dihitung, karena sudah ditangani lewat kolom country.
     *
     * @param  array{label: array<int, string>, induk: array<int, int|null>, negara: list<string>}  $indeks
     */
    private function tingkatProvinsi(int $id, array $indeks): bool
    {
        $induk = $indeks['induk'][$id] ?? null;

        if ($induk === null) {
            return ! in_array($indeks['label'][$id], $indeks['negara'], true);
        }

        return ($indeks['induk'][$induk] ?? null) === null;
    }

    /**
     * @return array{label: array<int, string>, induk: array<int, int|null>, negara: list<string>}
     */
    private function indeksWilayah(): array
    {
        return Cache::remember('wilayah.indeks.v1', now()->addSeconds(90), function (): array {
            $label = [];
            $induk = [];

            foreach (Region::query()->get(['id', 'label', 'parent_id']) as $region) {
                $label[(int) $region->id] = $this->normalisasi((string) $region->label);
                $induk[(int) $region->id] = $region->parent_id === null ? null : (int) $region->parent_id;
            }

            return [
                'label' => $label,
                'induk' => $induk,
                'negara' => Country::query()->pluck('label')
                    ->map(fn ($nama): string => $this->normalisasi((string) $nama))
                    ->all(),
            ];
        });
    }

    /**
     * Saran singkat untuk dropdown autocomplete.
     *
     * @return list<array{judul: string, sub: string, url: string, iata: string|null}>
     */
    public function saran(string $q, int $limit = 8): array
    {
        $q = trim($q);

        if ($q === '') {
            return [];
        }

        $hasil = $this->cari(['q' => $q, 'per_page' => $limit])->items();

        if ($hasil === []) {
            $hasil = $this->saranFuzzy($q, $limit);
        }

        return array_values(array_map(fn (array $row): array => [
            'judul' => $row['nama'],
            'sub' => collect([$row['iata'], $row['kota'], $row['wilayah'], $row['negara']])->filter()->unique()->implode(' · '),
            'url' => route('bandara.show', $row['iata']),
            'iata' => $row['iata'],
        ], array_slice($hasil, 0, $limit)));
    }

    /**
     * @return Collection<int, Airline>
     */
    public function maskapai(string $q, int $limit = 5): Collection
    {
        $q = trim($q);

        if ($q === '') {
            return new Collection;
        }

        $like = '%'.$q.'%';

        return Airline::query()
            ->with('hubAirport')
            ->withCount('routes')
            ->where(fn (Builder $builder) => $builder
                ->whereLike('label', $like)
                ->orWhereLike('airline_code', $like))
            ->orderByDesc('routes_count')
            ->orderBy('label')
            ->limit($limit)
            ->get();
    }

    /**
     * Cari rute dari dua kode IATA, misalnya "CGK DPS".
     *
     * @return Collection<int, Route>
     */
    public function rute(string $q, int $limit = 5): Collection
    {
        preg_match_all('/(?<![A-Za-z])([A-Za-z]{3})(?![A-Za-z])/u', $q, $cocok);
        $kode = array_values(array_unique(array_map('strtoupper', $cocok[1] ?? [])));

        if (count($kode) < 2) {
            return new Collection;
        }

        return Route::query()
            ->with(['originAirport', 'destinationAirport', 'airline'])
            ->whereHas('originAirport', fn (Builder $builder) => $builder->where('iata_code', $kode[0]))
            ->whereHas('destinationAirport', fn (Builder $builder) => $builder->where('iata_code', $kode[1]))
            ->orderBy('airline_id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $opsi
     */
    private function queryDasar(array $opsi): Builder
    {
        $negara = trim((string) ($opsi['negara'] ?? ''));
        $kategori = trim((string) ($opsi['kategori'] ?? ''));
        $asean = (string) ($opsi['asean'] ?? '');
        $punyaRute = (string) ($opsi['rute'] ?? '');

        return Airport::query()
            ->with(['country', 'region.parent.parent.parent.parent', 'aliases'])
            ->withCount('departureRoutes')
            ->when($negara !== '', function (Builder $builder) use ($negara): void {
                $builder->where(function (Builder $inner) use ($negara): void {
                    $inner->whereHas('country', fn (Builder $country) => $country->where('label', $negara))
                        ->orWhereHas('country', fn (Builder $country) => $country->where('id', (int) $negara));
                });
            })
            ->when($kategori !== '', fn (Builder $builder) => $builder->where('category', $kategori))
            ->when($asean === '1', fn (Builder $builder) => $builder->where('in_asean', true))
            ->when($asean === '0', fn (Builder $builder) => $builder->where('in_asean', false))
            ->when($punyaRute === '1', fn (Builder $builder) => $builder->has('departureRoutes'));
    }

    /**
     * @param  list<string>  $token
     */
    private function skor(Airport $airport, string $q, array $token, bool $semuaKata = false, bool $toleransiTypo = true): int
    {
        $label = $this->normalisasi((string) $airport->label);
        $qNormal = $this->normalisasi($q);
        $iata = strtoupper(trim((string) $airport->iata_code));
        $icao = strtoupper(trim((string) $airport->icao_code));
        $alias = $airport->aliases->pluck('alias')->map(fn ($nilai): string => $this->normalisasi((string) $nilai));
        $kataAlias = $alias->flatMap(fn (string $nilai): array => $this->kata($nilai))->unique()->values();
        $wilayah = $this->rantaiWilayah($airport);
        $negara = $this->normalisasi((string) $airport->country?->label);
        $operator = $this->normalisasi((string) $airport->operator);
        $kataLabel = $this->kata($label);
        $skor = 0;

        if ($qNormal === $this->normalisasi($iata) || $qNormal === $this->normalisasi($icao)) {
            $skor += 1200;
        }

        if ($alias->contains($qNormal)) {
            $skor += 1100;
        }

        if ($label === $qNormal) {
            $skor += 1000;
        } elseif (str_starts_with($label, $qNormal)) {
            $skor += 800;
        } elseif (str_contains($label, $qNormal)) {
            $skor += 650;
        }

        if (str_replace(' ', '', $label) === str_replace(' ', '', $qNormal)) {
            $skor += 700;
        }

        $wilayahTeks = implode(' ', $wilayah);

        if ($wilayah !== []) {
            if (in_array($qNormal, $wilayah, true)) {
                $skor += 700;
            } elseif (str_contains($wilayahTeks, $qNormal)) {
                $skor += 500;
            }
        }

        foreach ($token as $kata) {
            $nilai = 0;

            if ($kata === $this->normalisasi($iata) || $kata === $this->normalisasi($icao)) {
                $nilai = 900;
            } elseif (in_array($kata, $kataLabel, true)) {
                $nilai = 240;
            } elseif (collect($kataLabel)->contains(fn (string $labelKata): bool => str_starts_with($labelKata, $kata))) {
                $nilai = 190;
            } elseif ($kataAlias->contains($kata)) {
                $nilai = 500;
            } elseif (str_contains($label, $kata)) {
                $nilai = 150;
            } elseif ($alias->contains(fn (string $nilaiAlias): bool => str_contains($nilaiAlias, $kata))) {
                $nilai = 250;
            } elseif (collect($wilayah)->contains(fn (string $namaWilayah): bool => str_contains($namaWilayah, $kata))) {
                $nilai = 130;
            } elseif (str_contains($negara, $kata)) {
                $nilai = 90;
            } elseif (str_contains($operator, $kata)) {
                $nilai = 70;
            }

            if ($nilai === 0 && $toleransiTypo) {
                $nilai = $this->skorMirip($kata, array_merge($kataLabel, $alias->all(), $wilayah));
            }

            if ($nilai === 0 && $semuaKata) {
                return 0;
            }

            $skor += $nilai;
        }

        if ($skor === 0 && $toleransiTypo) {
            $skor = $this->skorMirip($qNormal, array_merge($kataLabel, $alias->all(), $wilayah));
        }

        return $skor;
    }

    /**
     * Rantai wilayah bandara dari yang terdekat sampai provinsi/negara bagian.
     *
     * Label negara dikeluarkan karena sudah dicocokkan lewat kolom country.
     *
     * @return list<string>
     */
    private function rantaiWilayah(Airport $airport): array
    {
        $rantai = [];
        $region = $airport->region;
        $negara = $this->normalisasi((string) $airport->country?->label);
        $kedalaman = 0;

        while ($region !== null && $kedalaman < 6) {
            $label = $this->normalisasi((string) $region->label);

            if ($label !== '' && $label !== $negara) {
                $rantai[] = $label;
            }

            $region = $region->parent;
            $kedalaman++;
        }

        return $rantai;
    }

    private function labelWilayah(Airport $airport): ?string
    {
        // Rantai dari induk terdekat sampai provinsi (tanpa nama negara), mis.
        // "Tapanuli Utara, Sumatera Utara". Bandara di kecamatan tetap menampilkan provinsinya.
        $negara = $airport->country?->label;
        $rantai = [];
        $region = $airport->region?->parent;

        for ($kedalaman = 0; $region !== null && $kedalaman < 6; $kedalaman++) {
            if ($region->label !== $negara) {
                $rantai[] = $region->label;
            }

            $region = $region->parent;
        }

        return $rantai === [] ? null : implode(', ', $rantai);
    }

    /**
     * @param  list<string>  $kandidat
     */
    private function skorMirip(string $kata, array $kandidat): int
    {
        $terbaik = 0;

        foreach ($kandidat as $nilai) {
            $nilai = trim($nilai);

            if ($nilai === '' || abs(mb_strlen($nilai) - mb_strlen($kata)) > 2) {
                continue;
            }

            $jarak = levenshtein($kata, $nilai);

            if ($jarak <= 2) {
                $terbaik = max($terbaik, 400 - ($jarak * 120));
            }
        }

        return $terbaik;
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return list<array<string, mixed>>
     */
    private function urutkan(array $baris, bool $berdasarkanJarak, string $urut): array
    {
        usort($baris, function (array $a, array $b) use ($berdasarkanJarak, $urut): int {
            if ($berdasarkanJarak) {
                return ($a['jarak_km'] <=> $b['jarak_km']) ?: ($b['skor'] <=> $a['skor']);
            }

            if ($urut === 'nama') {
                return strcasecmp($a['nama'], $b['nama']);
            }

            if ($urut === 'penumpang') {
                return (($b['penumpang'] ?? 0) <=> ($a['penumpang'] ?? 0)) ?: strcasecmp($a['nama'], $b['nama']);
            }

            return ($b['skor'] <=> $a['skor'])
                ?: (($b['penumpang'] ?? 0) <=> ($a['penumpang'] ?? 0))
                ?: (($b['rute_count'] ?? 0) <=> ($a['rute_count'] ?? 0))
                ?: strcasecmp($a['nama'], $b['nama']);
        });

        return $baris;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function saranFuzzy(string $q, int $limit): array
    {
        $qNormal = $this->normalisasi($q);
        $hasil = [];

        foreach (Airport::query()->with(['country', 'region.parent', 'aliases'])->get() as $airport) {
            $kandidat = array_merge(
                $this->kata($this->normalisasi((string) $airport->label)),
                [$this->normalisasi((string) $airport->local_name)]
            );
            $skor = $this->skorMirip($qNormal, $kandidat);

            if ($skor > 0) {
                $hasil[] = $this->baris($airport, $skor);
            }
        }

        usort($hasil, fn (array $a, array $b): int => ($b['skor'] <=> $a['skor']) ?: strcasecmp($a['nama'], $b['nama']));

        return array_slice($hasil, 0, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function baris(Airport $airport, int $skor = 0, ?float $jarak = null): array
    {
        return [
            'iata' => $airport->iata_code,
            'nama' => $airport->label,
            'kota' => $airport->region?->label,
            'wilayah' => $this->labelWilayah($airport),
            'negara' => $airport->country?->label,
            'kategori' => $airport->category,
            'operator' => $airport->operator,
            'in_asean' => (bool) $airport->in_asean,
            'lat' => $airport->latitude,
            'lon' => $airport->longitude,
            'runway' => $airport->longest_runway_length,
            'penumpang' => $airport->annual_passengers,
            'rute_count' => (int) ($airport->departure_routes_count ?? 0),
            'skor' => $skor,
            'jarak_km' => $jarak,
        ];
    }

    public function normalisasi(?string $teks): string
    {
        $teks = Str::ascii(mb_strtolower(trim((string) $teks)));
        $teks = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $teks) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $teks) ?? '');
    }

    /**
     * @return list<string>
     */
    public function token(string $teks): array
    {
        $normal = $this->normalisasi($teks);
        $token = array_values(array_filter(
            explode(' ', $normal),
            fn (string $kata): bool => $kata !== '' && ! in_array($kata, self::KATA_UMUM, true)
        ));

        return $token === [] ? array_values(array_filter(explode(' ', $normal))) : $token;
    }

    /**
     * @return list<string>
     */
    private function kata(string $teks): array
    {
        return array_values(array_filter(explode(' ', $this->normalisasi($teks))));
    }

    /**
     * Tandai kata yang cocok tanpa membocorkan HTML dari data.
     *
     * @param  list<string>  $token
     */
    public function highlight(?string $teks, array $token): string
    {
        $aman = e((string) $teks);

        foreach ($token as $kata) {
            $kata = trim($kata);

            if (mb_strlen($kata) < 2) {
                continue;
            }

            $aman = preg_replace(
                '/'.preg_quote(e($kata), '/').'/iu',
                '<mark>$0</mark>',
                $aman,
                1
            ) ?? $aman;
        }

        return $aman;
    }
}
