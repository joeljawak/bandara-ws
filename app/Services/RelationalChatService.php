<?php

namespace App\Services;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Region;
use App\Models\Route;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Menjawab pertanyaan chat langsung dari database relasional hasil impor RDF.
 *
 * Dipakai sebagai jalur utama ketika tabel bandara sudah terisi, sehingga chat
 * tetap berfungsi meskipun Fuseki tidak berjalan. Pengenalan maksud pertanyaan
 * memakai ulang ChatService::parse() supaya hasilnya konsisten.
 */
class RelationalChatService
{
    public function __construct(private AirportSearch $search) {}

    public function tersedia(): bool
    {
        return Schema::hasTable('airports') && Airport::query()->exists();
    }

    /**
     * @return array{text: string, items: array<int, array{judul: string, sub: string, url: string}>, saran: array<int, string>}
     */
    public function answer(string $pesan): array
    {
        $p = ChatService::parse($pesan);
        $p['wilayah'] = $this->deteksiWilayah($pesan, $p['negara']);

        return match ($p['intent']) {
            'sapaan' => $this->jawab('Halo! Aku bisa membantu mencari informasi bandara di Asia Tenggara. Coba salah satu pertanyaan ini:', [], true),
            'bantuan' => $this->jawab('Aku bisa menjawab jumlah bandara, bandara paling ramai, landasan terpanjang, daftar bandara per negara, info satu bandara, rute, dan maskapai. Contohnya:', [], true),
            'hitung' => $this->hitung($p),
            'ramai' => $this->peringkat($p, 'penumpang'),
            'landasan' => $this->peringkat($p, 'landasan'),
            'daftar' => $this->daftar($p),
            'rute' => $this->rute($p),
            'rute_ab' => $this->ruteAB($p),
            'maskapai' => $this->maskapai($p),
            default => $this->info($p),
        };
    }

    /**
     * @param  array<int, array{judul: string, sub: string, url: string}>  $items
     * @return array{text: string, items: array<int, array{judul: string, sub: string, url: string}>, saran: array<int, string>}
     */
    private function jawab(string $text, array $items = [], bool $saran = false): array
    {
        return ['text' => $text, 'items' => $items, 'saran' => $saran ? ChatService::contoh() : []];
    }

    private function angka(int|float|string|null $n): string
    {
        return number_format((float) $n, 0, ',', '.');
    }

    private function link(Airport $airport): string
    {
        if ($airport->iata_code) {
            return route('bandara.show', $airport->iata_code);
        }

        return route('bandara.index', ['q' => $airport->label]);
    }

    /**
     * @param  array<int, string>|null  $tokens
     */
    private function scopeNegara(Builder $query, ?array $tokens): Builder
    {
        if (! $tokens) {
            return $query;
        }

        return $query->whereHas('country', function (Builder $country) use ($tokens): void {
            $country->where(function (Builder $inner) use ($tokens): void {
                foreach ($tokens as $token) {
                    $inner->orWhereRaw('LOWER(label) LIKE ?', ['%'.mb_strtolower($token).'%']);
                }
            });
        });
    }

    /**
     * @param  array{id: int, label: string, ids: list<int>}|null  $wilayah
     */
    private function scopeWilayah(Builder $query, ?array $wilayah): Builder
    {
        if ($wilayah === null) {
            return $query;
        }

        return $query->whereIn('region_id', $wilayah['ids']);
    }

    /**
     * @return list<array{id: int, label: string, parent_id: int|null}>
     */
    private function petaWilayah(): array
    {
        return Cache::remember('chat.wilayah', now()->addHour(), fn (): array => Region::query()
            ->get(['id', 'label', 'parent_id'])
            ->map(fn (Region $region): array => [
                'id' => $region->id,
                'label' => (string) $region->label,
                'parent_id' => $region->parent_id,
            ])
            ->all());
    }

    /**
     * @return list<string>
     */
    private function namaNegaraTersimpan(): array
    {
        return Cache::remember('chat.negara', now()->addHour(), fn (): array => Country::query()
            ->pluck('label')
            ->map(fn ($label): string => $this->search->normalisasi((string) $label))
            ->filter()
            ->values()
            ->all());
    }

    /**
     * Temukan provinsi/wilayah yang disebut di pesan, lalu kembangkan ke seluruh
     * wilayah turunannya supaya pencarian ikut menjangkau kabupaten/kota.
     *
     * Wilayah hanya dipakai bila pesan tidak menyebut negara secara eksplisit.
     *
     * @param  array<int, string>|null  $negara
     * @return array{id: int, label: string, ids: list<int>}|null
     */
    private function deteksiWilayah(string $pesan, ?array $negara): ?array
    {
        if ($negara) {
            return null;
        }

        $normal = $this->search->normalisasi($pesan);

        if ($normal === '') {
            return null;
        }

        $labelNegara = $this->namaNegaraTersimpan();
        $peta = $this->petaWilayah();

        $cocok = collect($peta)
            ->filter(fn (array $region): bool => mb_strlen($this->search->normalisasi($region['label'])) >= 4)
            ->reject(fn (array $region): bool => in_array($this->search->normalisasi($region['label']), $labelNegara, true))
            ->filter(function (array $region) use ($normal): bool {
                $label = $this->search->normalisasi($region['label']);

                return $label !== '' && preg_match(
                    '/(?<![\p{L}])'.preg_quote($label, '/').'(?![\p{L}])/u',
                    $normal
                ) === 1;
            })
            ->sortByDesc(fn (array $region): int => mb_strlen((string) $region['label']))
            ->first();

        if ($cocok === null) {
            return null;
        }

        $anak = [];

        foreach ($peta as $region) {
            if ($region['parent_id'] !== null) {
                $anak[$region['parent_id']][] = $region['id'];
            }
        }

        $ids = [];
        $antre = [$cocok['id']];

        while ($antre !== []) {
            $id = array_pop($antre);
            $ids[] = $id;

            foreach ($anak[$id] ?? [] as $anakId) {
                $antre[] = $anakId;
            }
        }

        return [
            'id' => $cocok['id'],
            'label' => (string) $cocok['label'],
            'ids' => array_values(array_unique($ids)),
        ];
    }

    /**
     * @param  array{negara: array<int, string>|null, negara_nama: string|null, wilayah?: array{id: int, label: string, ids: list<int>}|null}  $p
     */
    private function namaScope(array $p): string
    {
        return $p['wilayah']['label'] ?? $this->namaNegara($p);
    }

    private function lokasi(Airport $airport): string
    {
        return collect([
            $airport->region?->label,
            $airport->region?->parent?->label,
            $airport->country?->label,
        ])->filter()->unique()->implode(', ');
    }

    /**
     * @param  array{negara: array<int, string>|null, negara_nama: string|null}  $p
     */
    private function namaNegara(array $p): string
    {
        if (! $p['negara']) {
            return 'Asia Tenggara';
        }

        $label = Country::query()
            ->where(function (Builder $query) use ($p): void {
                foreach ($p['negara'] as $token) {
                    $query->orWhereRaw('LOWER(label) LIKE ?', ['%'.mb_strtolower($token).'%']);
                }
            })
            ->value('label');

        return $label ?? ucwords((string) $p['negara_nama']);
    }

    /**
     * @param  array{negara: array<int, string>|null, negara_nama: string|null, wilayah?: array{id: int, label: string, ids: list<int>}|null}  $p
     */
    private function hitung(array $p): array
    {
        $n = $this->scopeWilayah($this->scopeNegara(Airport::query(), $p['negara']), $p['wilayah'] ?? null)->count();

        if ($n === 0) {
            return $this->jawab('Tidak ada bandara untuk '.$this->namaScope($p).' di data.');
        }

        return $this->jawab('Ada '.$this->angka($n).' bandara di '.$this->namaScope($p).' menurut data yang tersimpan.');
    }

    /**
     * @param  array{negara: array<int, string>|null, negara_nama: string|null, wilayah?: array{id: int, label: string, ids: list<int>}|null}  $p
     */
    private function peringkat(array $p, string $jenis): array
    {
        $kolom = $jenis === 'penumpang' ? 'annual_passengers' : 'longest_runway_length';

        $rows = $this->scopeWilayah(
            $this->scopeNegara(
                Airport::query()->with(['country', 'region.parent'])->whereNotNull($kolom),
                $p['negara']
            ),
            $p['wilayah'] ?? null
        )->orderByDesc($kolom)->limit(5)->get();

        if ($rows->isEmpty()) {
            return $this->jawab('Belum ada data '.($jenis === 'penumpang' ? 'jumlah penumpang' : 'panjang landasan').' untuk '.$this->namaScope($p).'.');
        }

        $items = $rows->map(fn (Airport $airport): array => [
            'judul' => $airport->label.' ('.$airport->iata_code.')',
            'sub' => $this->angka($airport->{$kolom}).($jenis === 'penumpang' ? ' penumpang per tahun' : ' meter').', '.($this->lokasi($airport) ?: '-'),
            'url' => $this->link($airport),
        ])->all();

        $judul = $jenis === 'penumpang'
            ? 'Bandara dengan penumpang terbanyak di '.$this->namaScope($p).' menurut data:'
            : 'Bandara dengan landasan terpanjang di '.$this->namaScope($p).' menurut data:';

        return $this->jawab($judul, $items);
    }

    /**
     * @param  array{negara: array<int, string>|null, negara_nama: string|null, wilayah?: array{id: int, label: string, ids: list<int>}|null}  $p
     */
    private function daftar(array $p): array
    {
        $rows = $this->scopeWilayah(
            $this->scopeNegara(Airport::query()->with(['country', 'region.parent']), $p['negara']),
            $p['wilayah'] ?? null
        )
            ->orderBy('label')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return $this->jawab('Aku tidak menemukan bandara untuk '.$this->namaScope($p).'.');
        }

        $items = $rows->map(fn (Airport $airport): array => [
            'judul' => $airport->label.' ('.($airport->iata_code ?? '-').')',
            'sub' => $this->lokasi($airport) ?: 'Lokasi belum tersedia',
            'url' => $this->link($airport),
        ])->all();

        $items[] = [
            'judul' => 'Lihat semua bandara di '.$this->namaScope($p),
            'sub' => 'Buka halaman data bandara',
            'url' => route('data.airports', ['q' => $this->namaScope($p)]),
        ];

        return $this->jawab('Ini beberapa bandara di '.$this->namaScope($p).' (urut abjad):', $items);
    }

    private function cari(?string $term): ?Airport
    {
        $term = trim((string) $term);
        if ($term === '') {
            return null;
        }

        $baris = $this->search->cari(['q' => $term, 'per_page' => 1])->items();

        if ($baris === []) {
            return null;
        }

        return Airport::query()
            ->with(['country', 'region.parent'])
            ->where('iata_code', $baris[0]['iata'])
            ->first();
    }

    /**
     * @param  array{term: string|null, wilayah?: array{id: int, label: string, ids: list<int>}|null}  $p
     */
    private function info(array $p): array
    {
        if (($p['wilayah'] ?? null) !== null) {
            return $this->daftar($p);
        }

        if ($p['term'] === null) {
            return $this->jawab('Bandara mana yang ingin kamu ketahui? Coba ketik nama atau kode IATA-nya, misalnya "Info Kualanamu".', [], true);
        }

        $hasil = $this->search->cari(['q' => $p['term'], 'per_page' => 8]);

        if ($hasil->total() === 0) {
            return $this->jawab('Aku belum menemukan bandara untuk "'.$p['term'].'". Coba nama lain, kode IATA, atau nama kotanya.', [], true);
        }

        $utama = collect($hasil->items())->first(
            fn (array $row): bool => strtoupper((string) $row['iata']) === strtoupper((string) $p['term'])
        ) ?? ($hasil->total() === 1 ? $hasil->items()[0] : null);

        if ($utama) {
            $airport = Airport::query()
                ->with(['country', 'region.parent'])
                ->where('iata_code', $utama['iata'])
                ->first();

            if ($airport) {
                return $this->kartu($airport);
            }
        }

        $items = collect($hasil->items())->take(6)->map(fn (array $row): array => [
            'judul' => $row['nama'].' ('.($row['iata'] ?? '-').')',
            'sub' => collect([$row['kota'], $row['wilayah'] ?? null, $row['negara']])->filter()->unique()->implode(', '),
            'url' => route('bandara.show', $row['iata']),
        ])->all();

        return $this->jawab('Aku menemukan '.$hasil->total().' bandara yang cocok. Pilih salah satu:', $items);
    }

    private function kartu(Airport $airport): array
    {
        $lokasi = $this->lokasi($airport);
        $kalimat = [$airport->label.' ('.$airport->iata_code.($airport->icao_code ? ' / '.$airport->icao_code : '').') berada di '.($lokasi !== '' ? $lokasi : 'lokasi yang belum tercatat').'.'];

        if ($airport->operator) {
            $kalimat[] = 'Dikelola oleh '.$airport->operator.'.';
        }
        if ($airport->longest_runway_length !== null) {
            $kalimat[] = 'Landasan terpanjangnya '.$this->angka($airport->longest_runway_length).' meter.';
        }
        if ($airport->annual_passengers !== null) {
            $kalimat[] = 'Penumpangnya sekitar '.$this->angka($airport->annual_passengers).' per tahun.';
        }

        return $this->jawab(implode(' ', $kalimat), [[
            'judul' => 'Buka halaman '.$airport->label,
            'sub' => 'Foto, peta, rute, dan maskapai',
            'url' => $this->link($airport),
        ]]);
    }

    /**
     * @param  array{term: string|null}  $p
     */
    private function rute(array $p): array
    {
        $airport = $this->cari($p['term']);
        if (! $airport) {
            return $this->jawab('Dari bandara mana? Coba tulis seperti "Rute dari KNO" atau "Penerbangan dari Changi".', [], true);
        }

        $routes = Route::query()
            ->with(['destinationAirport.country'])
            ->where('origin_airport_id', $airport->id)
            ->get()
            ->groupBy('destination_airport_id')
            ->map(fn ($group) => [
                'airport' => $group->first()->destinationAirport,
                'jml' => $group->pluck('airline_id')->unique()->count(),
            ])
            ->sortByDesc('jml')
            ->take(10)
            ->values();

        if ($routes->isEmpty()) {
            return $this->jawab('Data rute dari '.$airport->label.' belum tersedia.', [[
                'judul' => $airport->label,
                'sub' => 'Buka halaman bandara',
                'url' => $this->link($airport),
            ]]);
        }

        $items = $routes->map(function (array $row): array {
            /** @var Airport $destination */
            $destination = $row['airport'];

            return [
                'judul' => $destination->label.' ('.($destination->iata_code ?? '-').')',
                'sub' => $row['jml'].' maskapai',
                'url' => $this->link($destination),
            ];
        })->all();

        return $this->jawab('Rute dari '.$airport->label.' ('.$airport->iata_code.'), tujuan dengan maskapai terbanyak lebih dulu:', $items);
    }

    /**
     * @param  array{dari: string|null, ke: string|null}  $p
     */
    private function ruteAB(array $p): array
    {
        $asal = $this->cari($p['dari']);
        $tujuan = $this->cari($p['ke']);

        if (! $asal || ! $tujuan) {
            $yang = ! $asal ? $p['dari'] : $p['ke'];

            return $this->jawab('Aku belum menemukan bandara "'.$yang.'". Coba nama lain atau kode IATA.', [], true);
        }

        $peta = [[
            'judul' => 'Lihat rute '.$asal->iata_code.' ke '.$tujuan->iata_code,
            'sub' => 'Daftar rute di halaman data',
            'url' => route('data.routes', ['origin' => $asal->id, 'destination' => $tujuan->id]),
        ]];

        $maskapai = Route::query()
            ->with('airline')
            ->where('origin_airport_id', $asal->id)
            ->where('destination_airport_id', $tujuan->id)
            ->get()
            ->pluck('airline.label')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($maskapai->isEmpty()) {
            return $this->jawab('Di data tidak ada rute langsung dari '.$asal->label.' ke '.$tujuan->label.'.', $peta);
        }

        return $this->jawab('Rute langsung '.$asal->label.' ke '.$tujuan->label.' dilayani oleh: '.$maskapai->implode(', ').'.', $peta);
    }

    /**
     * @param  array{term: string|null}  $p
     */
    private function maskapai(array $p): array
    {
        $airport = $this->cari($p['term']);
        if (! $airport) {
            return $this->jawab('Maskapai di bandara mana? Coba tulis seperti "Maskapai di CGK".', [], true);
        }

        $nama = Route::query()
            ->with('airline')
            ->where('origin_airport_id', $airport->id)
            ->get()
            ->pluck('airline.label')
            ->filter()
            ->unique()
            ->sort()
            ->take(20)
            ->values();

        if ($nama->isEmpty()) {
            return $this->jawab('Data maskapai untuk '.$airport->label.' belum tersedia.');
        }

        return $this->jawab(
            'Maskapai yang melayani penerbangan dari '.$airport->label.': '.$nama->implode(', ').'.',
            [[
                'judul' => 'Buka halaman '.$airport->label,
                'sub' => 'Foto, peta, rute, dan maskapai',
                'url' => $this->link($airport),
            ]]
        );
    }
}
