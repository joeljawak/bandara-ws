<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\Airport;
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
 * Kandidat diambil dari tabel airports (maksimal beberapa ratus baris), lalu
 * diberi skor di PHP. Cara ini cukup cepat untuk dataset ASEAN dan membuat
 * typo tolerance serta alias tidak bergantung pada fitur khusus MySQL.
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
        $page = max(1, (int) ($opsi['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($opsi['per_page'] ?? self::PER_PAGE)));
        $near = Geo::parseKoordinat($opsi['near'] ?? null);
        $radius = max(1, min(2000, (int) ($opsi['radius'] ?? 100)));
        $urut = (string) ($opsi['urut'] ?? 'relevan');

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
        ]));

        $baris = Cache::remember($kunci, now()->addSeconds(90), function () use ($query, $near, $radius, $q, $token, $urut): array {
            $hasil = [];

            foreach ($query->get() as $airport) {
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

                $skor = $q === '' ? 1 : $this->skor($airport, $q, $token);

                if ($skor <= 0) {
                    continue;
                }

                $hasil[] = $this->baris($airport, $skor, $jarak);
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
    private function skor(Airport $airport, string $q, array $token): int
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

            if ($nilai === 0) {
                $nilai = $this->skorMirip($kata, array_merge($kataLabel, $alias->all(), $wilayah));
            }

            $skor += $nilai;
        }

        if ($skor === 0) {
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
        $negara = $airport->country?->label;
        $region = $airport->region?->parent;

        while ($region !== null) {
            if ($region->label !== $negara) {
                return $region->label;
            }

            $region = $region->parent;
        }

        return null;
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
