<?php

namespace App\Services;

use App\Models\Airport;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Pencarian bandara dari tabel relasional.
 * Selain nama/kode/kota/negara, query juga dicocokkan ke wilayah (kabupaten, kota, provinsi),
 * jadi "sumatera utara" menampilkan semua bandara di provinsi itu.
 */
class AirportRegionSearch
{
    // nama Inggris -> nama di data (Indonesia)
    private const ALIAS = [
        'north sumatra' => 'sumatera utara',
        'west sumatra' => 'sumatera barat',
        'south sumatra' => 'sumatera selatan',
        'west java' => 'jawa barat',
        'central java' => 'jawa tengah',
        'east java' => 'jawa timur',
        'north sulawesi' => 'sulawesi utara',
        'south sulawesi' => 'sulawesi selatan',
        'west kalimantan' => 'kalimantan barat',
        'east kalimantan' => 'kalimantan timur',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $q, int $limit = 100): array
    {
        $term = $this->normalize($q);

        if ($term === '') {
            return [];
        }

        $term = self::ALIAS[$term] ?? $term;
        $like = '%'.addcslashes($term, '%_\\').'%';
        $regionIds = mb_strlen($term) >= 3 ? $this->regionIds($like) : [];

        $airports = Airport::query()
            ->with(['country', 'region.parent.parent'])
            ->whereNotNull('iata_code')
            ->where(function (Builder $w) use ($like, $term, $regionIds): void {
                $w->whereLike('label', $like)
                    ->orWhereLike('local_name', $like)
                    ->orWhereLike('operator', $like)
                    ->orWhereRaw('lower(iata_code) = ?', [$term])
                    ->orWhereRaw('lower(icao_code) = ?', [$term])
                    ->orWhereHas('country', fn (Builder $c) => $c->whereLike('label', $like));

                if ($regionIds !== []) {
                    $w->orWhereIn('region_id', $regionIds);
                }
            })
            ->get();

        return $airports
            ->map(fn (Airport $a): array => $this->row($a, $term))
            ->sortBy([['skor', 'desc'], ['penumpang', 'desc'], ['nama', 'asc']])
            ->take($limit)
            ->values()
            ->all();
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(Str::ascii(trim($s)));

        return trim(preg_replace('/[\s\-]+/u', ' ', $s) ?? '');
    }

    /**
     * Id wilayah yang labelnya cocok, ditambah semua turunannya (provinsi -> kabupaten -> kecamatan).
     *
     * @return list<int>
     */
    private function regionIds(string $like): array
    {
        $found = Region::query()->whereLike('label', $like)->pluck('id')->all();
        $all = $found;

        for ($i = 0; $i < 4 && $found !== []; $i++) {
            $found = Region::query()
                ->whereIn('parent_id', $found)
                ->whereNotIn('id', $all)
                ->pluck('id')
                ->all();
            $all = array_merge($all, $found);
        }

        return $all;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Airport $a, string $term): array
    {
        $negara = $a->country?->label;

        // rantai wilayah dari yang paling kecil: Siborongborong, Tapanuli Utara, Sumatera Utara
        $rantai = [];
        for ($r = $a->region, $n = 0; $r && $n < 4; $r = $r->parent, $n++) {
            if ($r->label !== $negara) {
                $rantai[] = $r->label;
            }
        }
        $rantai = array_values(array_unique($rantai));

        return [
            'nama' => $a->label,
            'iata' => $a->iata_code,
            'icao' => $a->icao_code,
            'kota' => $rantai ? implode(', ', $rantai) : null,
            'negara' => $negara,
            'lat' => $a->latitude,
            'lon' => $a->longitude,
            'penumpang' => (int) $a->annual_passengers,
            'skor' => $this->skor($a, $term, $rantai, $negara),
        ];
    }

    /**
     * @param  list<string>  $rantai
     */
    private function skor(Airport $a, string $term, array $rantai, ?string $negara): int
    {
        $nama = $this->normalize($a->label);

        if ($this->normalize((string) $a->iata_code) === $term || $this->normalize((string) $a->icao_code) === $term) {
            return 100;
        }
        if ($nama === $term) {
            return 90;
        }
        if (str_starts_with($nama, $term)) {
            return 80;
        }
        if (str_contains(' '.$nama, ' '.$term)) {
            return 70;
        }
        if ($a->local_name && str_contains($this->normalize($a->local_name), $term)) {
            return 60;
        }

        foreach ($rantai as $i => $label) {
            $l = $this->normalize($label);
            if ($l === $term) {
                return $i === 0 ? 68 : 65;   // kota/kabupaten, atau provinsi
            }
            if (str_contains($l, $term)) {
                return 50;
            }
        }

        if ($negara !== null && str_contains($this->normalize($negara), $term)) {
            return 40;
        }

        return str_contains($nama, $term) ? 55 : 30;
    }
}
