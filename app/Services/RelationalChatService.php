<?php

namespace App\Services;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Route;
use Illuminate\Database\Eloquent\Builder;
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
            return route('data.airport', $airport->iata_code);
        }

        return route('data.airports', ['q' => $airport->label]);
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
     * @param  array{negara: array<int, string>|null, negara_nama: string|null}  $p
     */
    private function hitung(array $p): array
    {
        $n = $this->scopeNegara(Airport::query(), $p['negara'])->count();

        if ($n === 0) {
            return $this->jawab('Tidak ada bandara untuk '.$this->namaNegara($p).' di data.');
        }

        return $this->jawab('Ada '.$this->angka($n).' bandara di '.$this->namaNegara($p).' menurut data yang tersimpan.');
    }

    /**
     * @param  array{negara: array<int, string>|null, negara_nama: string|null}  $p
     */
    private function peringkat(array $p, string $jenis): array
    {
        $kolom = $jenis === 'penumpang' ? 'annual_passengers' : 'longest_runway_length';

        $rows = $this->scopeNegara(
            Airport::query()->with('country')->whereNotNull($kolom),
            $p['negara']
        )->orderByDesc($kolom)->limit(5)->get();

        if ($rows->isEmpty()) {
            return $this->jawab('Belum ada data '.($jenis === 'penumpang' ? 'jumlah penumpang' : 'panjang landasan').' untuk '.$this->namaNegara($p).'.');
        }

        $items = $rows->map(fn (Airport $airport): array => [
            'judul' => $airport->label.' ('.$airport->iata_code.')',
            'sub' => $this->angka($airport->{$kolom}).($jenis === 'penumpang' ? ' penumpang per tahun' : ' meter').', '.($airport->country?->label ?? '-'),
            'url' => $this->link($airport),
        ])->all();

        $judul = $jenis === 'penumpang'
            ? 'Bandara dengan penumpang terbanyak di '.$this->namaNegara($p).' menurut data:'
            : 'Bandara dengan landasan terpanjang di '.$this->namaNegara($p).' menurut data:';

        return $this->jawab($judul, $items);
    }

    /**
     * @param  array{negara: array<int, string>|null, negara_nama: string|null}  $p
     */
    private function daftar(array $p): array
    {
        $rows = $this->scopeNegara(Airport::query()->with('country'), $p['negara'])
            ->orderBy('label')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return $this->jawab('Aku tidak menemukan bandara untuk '.$this->namaNegara($p).'.');
        }

        $items = $rows->map(fn (Airport $airport): array => [
            'judul' => $airport->label.' ('.($airport->iata_code ?? '-').')',
            'sub' => $airport->country?->label ?? 'Negara belum tersedia',
            'url' => $this->link($airport),
        ])->all();

        $items[] = [
            'judul' => 'Lihat semua bandara'.($p['negara'] ? ' di '.$this->namaNegara($p) : ''),
            'sub' => 'Buka halaman data bandara',
            'url' => route('data.airports', $p['negara'] ? ['q' => $this->namaNegara($p)] : []),
        ];

        return $this->jawab('Ini beberapa bandara di '.$this->namaNegara($p).' (urut abjad):', $items);
    }

    private function cari(?string $term): ?Airport
    {
        $term = trim((string) $term);
        if ($term === '') {
            return null;
        }

        $exact = Airport::query()->with(['country', 'region'])
            ->whereRaw('LOWER(iata_code) = ?', [mb_strtolower($term)])
            ->first();

        if ($exact) {
            return $exact;
        }

        return Airport::query()->with(['country', 'region'])->search($term)->orderBy('label')->first();
    }

    /**
     * @param  array{term: string|null}  $p
     */
    private function info(array $p): array
    {
        if ($p['term'] === null) {
            return $this->jawab('Bandara mana yang ingin kamu ketahui? Coba ketik nama atau kode IATA-nya, misalnya "Info Kualanamu".', [], true);
        }

        $hasil = Airport::query()->with(['country', 'region'])->search($p['term'])->orderBy('label')->limit(50)->get();

        if ($hasil->isEmpty()) {
            return $this->jawab('Aku belum menemukan bandara untuk "'.$p['term'].'". Coba nama lain, kode IATA, atau nama kotanya.', [], true);
        }

        $utama = $hasil->first(fn (Airport $airport): bool => strtoupper((string) $airport->iata_code) === strtoupper((string) $p['term']))
            ?? ($hasil->count() === 1 ? $hasil->first() : null);

        if ($utama) {
            return $this->kartu($utama);
        }

        $items = $hasil->take(6)->map(fn (Airport $airport): array => [
            'judul' => $airport->label.' ('.($airport->iata_code ?? '-').')',
            'sub' => collect([$airport->region?->label, $airport->country?->label])->filter()->implode(', '),
            'url' => $this->link($airport),
        ])->all();

        return $this->jawab('Aku menemukan '.$hasil->count().' bandara yang cocok. Pilih salah satu:', $items);
    }

    private function kartu(Airport $airport): array
    {
        $lokasi = collect([$airport->region?->label, $airport->country?->label])->filter()->implode(', ');
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
