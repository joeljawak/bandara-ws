<?php

namespace App\Services;

use App\Support\Geo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Pencarian katalog bandara langsung di RDF store Fuseki.
 * Tidak mengunduh graph dan tidak memakai Eloquent/SQLite sebagai mesin pencarian.
 */
class AirportSparqlSearch
{
    public function __construct(private SparqlService $sparql) {}

    public function facets(): array
    {
        $countries = Cache::remember('sparql.airport.facets.countries', 300, function (): array {
            return $this->sparql->query('
                SELECT ?label (COUNT(DISTINCT ?a) AS ?total) WHERE {
                    ?a a ex:Airport ; ex:locatedInCountry ?c .
                    ?c rdfs:label ?label .
                } GROUP BY ?label ORDER BY LCASE(STR(?label))
            ');
        });

        $categories = Cache::remember('sparql.airport.facets.categories', 300, function (): array {
            return $this->sparql->query('
                SELECT DISTINCT ?category WHERE {
                    ?a a ex:Airport ; ex:airportCategory ?category .
                    FILTER(STR(?category) != "")
                } ORDER BY LCASE(STR(?category))
            ');
        });

        return [
            'countries' => array_map(fn (array $r): object => (object) [
                'label' => $r['label'], 'airports_count' => (int) ($r['total'] ?? 0),
            ], $countries),
            'categories' => array_values(array_filter(array_column($categories, 'category'))),
        ];
    }

    public function search(array $options = []): LengthAwarePaginator
    {
        $q = trim((string) ($options['q'] ?? ''));
        $country = trim((string) ($options['negara'] ?? ''));
        $category = trim((string) ($options['kategori'] ?? ''));
        $asean = (string) ($options['asean'] ?? '');
        $routeOnly = (string) ($options['rute'] ?? '');
        $sort = (string) ($options['urut'] ?? 'relevan');
        $page = max(1, (int) ($options['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($options['per_page'] ?? 15)));
        $near = Geo::parseKoordinat($options['near'] ?? null);
        $radius = max(1, min(2000, (int) ($options['radius'] ?? 100)));

        $where = [
            '?a a ex:Airport .',
            'OPTIONAL { ?a ex:hasIATACode ?iata }',
            'OPTIONAL { ?a ex:hasICAOCode ?icao }',
            'OPTIONAL { ?a ex:locatedInCountry ?c . ?c rdfs:label ?negara }',
            'OPTIONAL { ?a ex:locatedInRegion ?r . ?r rdfs:label ?kota }',
            'OPTIONAL { ?a ex:airportCategory ?kategori }',
            'OPTIONAL { ?a ex:airportStatus ?status }',
            'OPTIONAL { ?a ex:operator ?operator }',
            'OPTIONAL { ?a ex:inASEAN ?asean }',
            'OPTIONAL { ?a geo:lat ?lat }',
            'OPTIONAL { ?a geo:long ?lon }',
            'OPTIONAL { ?a ex:annualPassengers ?penumpang }',
            'OPTIONAL { ?a ex:longestRunwayLength ?runway }',
            'OPTIONAL { SELECT ?a (COUNT(DISTINCT ?tujuan) AS ?rute_count) WHERE { ?a ex:isDirectFlightTo ?tujuan } GROUP BY ?a }',
        ];

        if ($country !== '') {
            $where[] = 'FILTER(LCASE(STR(?negara)) = LCASE("'.SparqlService::esc($country).'"))';
        }
        if ($category !== '') {
            $where[] = 'FILTER(LCASE(STR(?kategori)) = LCASE("'.SparqlService::esc($category).'"))';
        }
        if ($asean === '1') {
            $where[] = 'FILTER(BOUND(?asean) && ?asean = true)';
        } elseif ($asean === '0') {
            $where[] = 'FILTER(!BOUND(?asean) || ?asean = false)';
        }
        if ($routeOnly === '1') {
            $where[] = 'FILTER(BOUND(?rute_count) && ?rute_count > 0)';
        }

        // Simpan filter non-pencarian agar fallback typo tetap menghormati
        // negara, kategori, ASEAN, dan filter rute yang dipilih pengguna.
        $fallbackWhere = $where;

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_slice($tokens, 0, 5);
        if ($tokens !== []) {
            $where[] = 'BIND(CONCAT(STR(?nama), " ", COALESCE(STR(?iata), ""), " ", COALESCE(STR(?icao), ""), " ", COALESCE(STR(?kota), ""), " ", COALESCE(STR(?negara), "")) AS ?teks)';
            foreach ($tokens as $token) {
                $where[] = 'FILTER('.SparqlService::regex('?teks', $token).')';
            }
        }
        if ($near !== null) {
            // Bounding box menekan jumlah hasil di Fuseki; radius presisi dihitung setelahnya.
            $latDelta = $radius / 110.574;
            $lonDelta = $radius / max(1, 111.320 * cos(deg2rad($near['lat'])));
            $where[] = 'FILTER(BOUND(?lat) && BOUND(?lon))';
            $where[] = 'FILTER(xsd:decimal(?lat) >= '.($near['lat'] - $latDelta).' && xsd:decimal(?lat) <= '.($near['lat'] + $latDelta).')';
            $where[] = 'FILTER(xsd:decimal(?lon) >= '.($near['lon'] - $lonDelta).' && xsd:decimal(?lon) <= '.($near['lon'] + $lonDelta).')';
        }

        $pattern = implode("\n", $where);
        $base = '?a rdfs:label ?nama . '.$pattern;
        $cacheKey = 'sparql.airports.search.'.md5(json_encode([$q, $country, $category, $asean, $routeOnly, $sort, $near, $radius, $page, $perPage]));

        $rows = Cache::remember($cacheKey, 45, function () use ($base, $near, $radius, $sort, $page, $perPage): array {
            $order = match ($sort) {
                'penumpang' => 'DESC(?penumpang) ?nama',
                default => 'LCASE(STR(?nama))',
            };
            $offset = ($page - 1) * $perPage;
            // Untuk pencarian radius, ambil semua kandidat di bounding box dahulu,
            // hitung jarak geodesik, lalu lakukan pagination setelah filter radius.
            $pagination = $near === null ? ' LIMIT '.$perPage.' OFFSET '.$offset : '';
            $query = 'SELECT DISTINCT ?iata ?nama ?kota ?negara ?kategori ?operator ?asean ?lat ?lon ?runway ?penumpang ?rute_count WHERE { '.$base.' } ORDER BY '.$order.$pagination;
            $result = $this->sparql->query($query);

            return array_map(function (array $r) use ($near): array {
                $distance = null;
                if ($near !== null && isset($r['lat'], $r['lon'])) {
                    $distance = Geo::jarakKm($near['lat'], $near['lon'], (float) $r['lat'], (float) $r['lon']);
                }
                return [
                    'iata' => $r['iata'] ?? null,
                    'nama' => $r['nama'] ?? '',
                    'kota' => $r['kota'] ?? null,
                    'wilayah' => $r['kota'] ?? null,
                    'negara' => $r['negara'] ?? null,
                    'kategori' => $r['kategori'] ?? null,
                    'operator' => $r['operator'] ?? null,
                    'in_asean' => filter_var($r['asean'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'lat' => isset($r['lat']) ? (float) $r['lat'] : null,
                    'lon' => isset($r['lon']) ? (float) $r['lon'] : null,
                    'runway' => isset($r['runway']) ? (float) $r['runway'] : null,
                    'penumpang' => isset($r['penumpang']) ? (int) $r['penumpang'] : null,
                    'rute_count' => (int) ($r['rute_count'] ?? 0),
                    'skor' => 0,
                    'jarak_km' => $distance,
                ];
            }, $result);
        });

        if ($near !== null) {
            $rows = array_values(array_filter($rows, fn (array $r): bool => $r['jarak_km'] !== null && $r['jarak_km'] <= $radius));
            usort($rows, fn (array $a, array $b): int => ($a['jarak_km'] ?? PHP_FLOAT_MAX) <=> ($b['jarak_km'] ?? PHP_FLOAT_MAX));
            $total = count($rows);
            $rows = array_slice($rows, ($page - 1) * $perPage, $perPage);
        } else {
            $total = Cache::remember($cacheKey.'.count', 45, function () use ($pattern): int {
                $countQuery = 'SELECT (COUNT(DISTINCT ?a) AS ?total) WHERE { ?a rdfs:label ?nama . '.$pattern.' }';
                return (int) ($this->sparql->query($countQuery)[0]['total'] ?? 0);
            });
        }

        // Pencarian utama di atas tetap SPARQL seperti sebelumnya.
        // Hanya jika benar-benar tidak ada hasil, jalankan fallback toleransi typo.
        if ($q !== '' && $total === 0 && $near === null) {
            [$rows, $total] = $this->searchTypoFallback(
                $fallbackWhere, $q, $page, $perPage, $sort
            );
        }

        return new LengthAwarePaginator($rows, $total, $perPage, $page, [
            'path' => request()->url(), 'query' => request()->query(),
        ]);
    }

    /**
     * Fallback typo untuk mode Fuseki. Tidak mengganti SPARQL pencarian utama:
     * fungsi ini hanya dipanggil saat pencarian utama menghasilkan nol data.
     *
     * @param list<string> $where Filter SPARQL selain kata kunci pencarian.
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function searchTypoFallback(array $where, string $q, int $page, int $perPage, string $sort): array
    {
        $pattern = implode("\n", $where);
        $query = 'SELECT DISTINCT ?iata ?nama ?kota ?negara ?kategori ?operator ?asean ?lat ?lon ?runway ?penumpang ?rute_count WHERE {
            ?a rdfs:label ?nama . '.$pattern.'
        } ORDER BY LCASE(STR(?nama)) LIMIT 5000';

        try {
            $bindings = $this->sparql->query($query);
        } catch (\Throwable $e) {
            report($e);
            return [[], 0];
        }

        $needle = $this->normalizeTypoText($q);
        $tokens = preg_split('/[^\\p{L}\\p{N}]+/u', mb_strtolower(trim($q)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ranked = [];

        foreach ($bindings as $binding) {
            $fields = [
                $this->normalizeTypoText((string) ($binding['nama'] ?? '')),
                $this->normalizeTypoText((string) ($binding['iata'] ?? '')),
                $this->normalizeTypoText((string) ($binding['kota'] ?? '')),
                $this->normalizeTypoText((string) ($binding['negara'] ?? '')),
            ];
            $candidateText = implode(' ', $fields);
            $score = 0;
            $allMatched = true;

            foreach ($tokens as $token) {
                $token = $this->normalizeTypoText($token);
                if ($token === '') continue;

                $best = PHP_INT_MAX;
                foreach ($fields as $field) {
                    foreach (preg_split('/\\s+/', $field, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                        $distance = levenshtein($token, $word);
                        // Terima pencarian parsial hanya jika kata yang dicari cukup panjang
                        // dan benar-benar merupakan bagian dari kata kandidat. Jangan anggap
                        // huruf pendek di dalam query sebagai kecocokan (mis. 'a' pada 'kualanmo').
                        if (mb_strlen($token) >= 4 && mb_strlen($word) >= 4 && str_contains($word, $token)) {
                            $distance = 0;
                        }
                        $best = min($best, $distance);
                    }
                }

                $allowed = mb_strlen($token) >= 7 ? 2 : (mb_strlen($token) >= 4 ? 1 : 0);
                if ($best > $allowed) {
                    $allMatched = false;
                    break;
                }
                $score += 100 - ($best * 30);
            }

            // Query satu kata tanpa spasi juga dicocokkan terhadap nama/kode utuh.
            if ($tokens === [] && $needle !== '') {
                $best = PHP_INT_MAX;
                foreach ($fields as $field) {
                    $best = min($best, levenshtein($needle, str_replace(' ', '', $field)));
                }
                if ($best > 2) $allMatched = false;
                else $score = 100 - ($best * 30);
            }

            if (!$allMatched || $score <= 0) continue;

            $ranked[] = [
                'iata' => $binding['iata'] ?? null,
                'nama' => $binding['nama'] ?? '',
                'kota' => $binding['kota'] ?? null,
                'wilayah' => $binding['kota'] ?? null,
                'negara' => $binding['negara'] ?? null,
                'kategori' => $binding['kategori'] ?? null,
                'operator' => $binding['operator'] ?? null,
                'in_asean' => filter_var($binding['asean'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'lat' => isset($binding['lat']) ? (float) $binding['lat'] : null,
                'lon' => isset($binding['lon']) ? (float) $binding['lon'] : null,
                'runway' => isset($binding['runway']) ? (float) $binding['runway'] : null,
                'penumpang' => isset($binding['penumpang']) ? (int) $binding['penumpang'] : null,
                'rute_count' => (int) ($binding['rute_count'] ?? 0),
                'skor' => $score,
                'jarak_km' => null,
            ];
        }

        usort($ranked, function (array $a, array $b) use ($sort): int {
            if ($a['skor'] !== $b['skor']) return $b['skor'] <=> $a['skor'];
            if ($sort === 'penumpang') return ($b['penumpang'] ?? 0) <=> ($a['penumpang'] ?? 0);
            return strcasecmp($a['nama'], $b['nama']);
        });

        $total = count($ranked);
        return [array_slice($ranked, ($page - 1) * $perPage, $perPage), $total];
    }

    private function normalizeTypoText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\\p{L}\\p{N}]+/u', ' ', $text) ?? $text;
        return trim(preg_replace('/\\s+/u', ' ', $text) ?? $text);
    }
}
