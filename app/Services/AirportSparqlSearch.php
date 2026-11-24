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
        $normalizedQuery = mb_strtolower(preg_replace('/\s+/u', ' ', trim($q)) ?? $q);
        $normalizedQuery = preg_replace('/^(?:kota|kabupaten|kab\.?|city of)\s+/u', '', $normalizedQuery) ?? $normalizedQuery;
        $searchQuery = match ($normalizedQuery) {
            // KNO berada di Deli Serdang, tetapi melayani Kota Medan.
            'medan', 'medan city' => 'kualanamu',
            // Singkatan provinsi Indonesia -> label lengkap yang dipakai RDF.
            'nad' => 'aceh',
            'sumut' => 'sumatera utara',
            'sumbar' => 'sumatera barat',
            'sumsel' => 'sumatera selatan',
            'kepri' => 'kepulauan riau',
            'babel' => 'kepulauan bangka belitung',
            'dki', 'dki jakarta' => 'jakarta',
            'jabar' => 'jawa barat',
            'jateng' => 'jawa tengah',
            'jatim' => 'jawa timur',
            'diy', 'jogja', 'jogjakarta' => 'yogyakarta',
            'ntb' => 'nusa tenggara barat',
            'ntt' => 'nusa tenggara timur',
            'kalbar' => 'kalimantan barat',
            'kalteng' => 'kalimantan tengah',
            'kalsel' => 'kalimantan selatan',
            'kaltim' => 'kalimantan timur',
            'kaltara' => 'kalimantan utara',
            'sulut' => 'sulawesi utara',
            'sulteng' => 'sulawesi tengah',
            'sulsel' => 'sulawesi selatan',
            'sultra' => 'sulawesi tenggara',
            'sulbar' => 'sulawesi barat',
            'malut' => 'maluku utara',
            'papbar', 'pabar' => 'papua barat',
            'pabardaya' => 'papua barat daya',
            'papteng' => 'papua tengah',
            'papeg' => 'papua pegunungan',
            'papsel' => 'papua selatan',
            'sumatera utara', 'north sumatra', 'north sumatera' => 'sumatera utara',
            // Singkatan wilayah internasional yang umum dipakai.
            'ca', 'calif' => 'california',
            'ny' => 'new york',
            'tx' => 'texas',
            'fl' => 'florida',
            'il' => 'illinois',
            'wa' => 'washington',
            'dc' => 'district of columbia',
            'bc' => 'british columbia',
            'ab' => 'alberta',
            'on' => 'ontario',
            'qc' => 'quebec',
            'nsw' => 'new south wales',
            'vic' => 'victoria',
            'qld' => 'queensland',
            'sa' => 'south australia',
            'wa australia' => 'western australia',
            // Nama negara, nama lokal, dan kode negara.
            'id', 'indo', 'indonesian' => 'indonesia',
            'ph', 'phl', 'philippines', 'philippine', 'pilipinas' => 'filipina',
            'sg' => 'singapura',
            'my' => 'malaysia',
            'th' => 'thailand',
            'vn', 'viet nam' => 'vietnam',
            'kh', 'cambodia' => 'kamboja',
            'la', 'lao pdr' => 'laos',
            'bn', 'brunei darussalam' => 'brunei',
            'mm', 'burma' => 'myanmar',
            'tl', 'east timor' => 'timor leste',
            'cn', 'chn', 'cina', 'tiongkok' => 'china',
            'jp', 'jepang' => 'japan',
            'kr', 'korea selatan' => 'south korea',
            'kp', 'korea utara' => 'north korea',
            'us', 'usa', 'u.s.', 'america', 'amerika serikat' => 'united states',
            'uk', 'gb', 'gbr', 'great britain', 'inggris' => 'united kingdom',
            'ae', 'uae' => 'united arab emirates',
            'saudi', 'arab saudi' => 'saudi arabia',
            'nz', 'selandia baru' => 'new zealand',
            'za', 'afrika selatan' => 'south africa',
            'de', 'jerman' => 'germany',
            'fr', 'prancis' => 'france',
            'nl', 'belanda' => 'netherlands',
            'ru', 'rusia' => 'russia',
            'pg', 'png' => 'papua new guinea',
            'sumatra' => 'sumatera',
            default => $normalizedQuery,
        };
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
            'OPTIONAL { ?a ex:locatedInRegion/ex:partOfRegion* ?wilayahR . ?wilayahR rdfs:label ?wilayah }',
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

        // Kata kunci tidak difilter dengan REGEX mentah di Fuseki karena REGEX
        // tidak menormalkan diakritik (mis. Hải Phòng vs "hai phong").
        // Semua kandidat yang lolos filter struktural diambil satu kali, lalu
        // dicocokkan dengan normalisasi + ranking di searchTypoFallback().
        if ($near !== null) {
            $latDelta = $radius / 110.574;
            $lonDelta = $radius / max(1, 111.320 * cos(deg2rad($near['lat'])));
            $where[] = 'FILTER(BOUND(?lat) && BOUND(?lon))';
            $where[] = 'FILTER(xsd:decimal(?lat) >= '.($near['lat'] - $latDelta).' && xsd:decimal(?lat) <= '.($near['lat'] + $latDelta).')';
            $where[] = 'FILTER(xsd:decimal(?lon) >= '.($near['lon'] - $lonDelta).' && xsd:decimal(?lon) <= '.($near['lon'] + $lonDelta).')';
        }

        $pattern = implode("\n", $where);
        $base = '?a rdfs:label ?nama . '.$pattern;
        $cacheKey = 'sparql.airports.search.'.md5(json_encode([
            $q, $searchQuery, $country, $category, $asean, $routeOnly,
            $sort, $near, $radius, $page, $perPage, 'v6-adaptive-fuzzy-typo',
        ]));

        if ($searchQuery !== '') {
            [$rows, $total] = Cache::remember($cacheKey.'.ranked', 45, function () use (
                $where, $searchQuery, $page, $perPage, $sort, $near, $radius
            ): array {
                return $this->searchTypoFallback(
                    $where, $searchQuery, $page, $perPage, $sort, $near, $radius
                );
            });
        } else {
            $rows = Cache::remember($cacheKey, 45, function () use ($base, $near, $radius, $sort, $page, $perPage): array {
                $order = match ($sort) {
                    'penumpang' => 'DESC(?out_penumpang) ?out_nama',
                    default => 'LCASE(STR(?out_nama))',
                };
                $offset = ($page - 1) * $perPage;
                $pagination = $near === null ? ' LIMIT '.$perPage.' OFFSET '.$offset : '';
                $query = 'SELECT ?a (SAMPLE(?iata) AS ?out_iata) (SAMPLE(?icao) AS ?out_icao) (SAMPLE(?nama) AS ?out_nama) (SAMPLE(?kota) AS ?out_kota) (SAMPLE(?wilayah) AS ?out_wilayah) (SAMPLE(?negara) AS ?out_negara) (SAMPLE(?kategori) AS ?out_kategori) (SAMPLE(?operator) AS ?out_operator) (SAMPLE(?asean) AS ?out_asean) (SAMPLE(?lat) AS ?out_lat) (SAMPLE(?lon) AS ?out_lon) (SAMPLE(?runway) AS ?out_runway) (SAMPLE(?penumpang) AS ?out_penumpang) (SAMPLE(?rute_count) AS ?out_rute_count) WHERE { '.$base.' } GROUP BY ?a ORDER BY '.$order.$pagination;
                $result = $this->sparql->query($query);

                return array_map(function (array $r) use ($near): array {
                    $distance = null;
                    if ($near !== null && isset($r['out_lat'], $r['out_lon'])) {
                        $distance = Geo::jarakKm($near['lat'], $near['lon'], (float) $r['out_lat'], (float) $r['out_lon']);
                    }
                    return [
                        'iata' => $r['out_iata'] ?? null,
                        'nama' => $r['out_nama'] ?? '',
                        'kota' => $r['out_kota'] ?? null,
                        'wilayah' => $r['out_wilayah'] ?? ($r['out_kota'] ?? null),
                        'negara' => $r['out_negara'] ?? null,
                        'kategori' => $r['out_kategori'] ?? null,
                        'operator' => $r['out_operator'] ?? null,
                        'in_asean' => filter_var($r['out_asean'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'lat' => isset($r['out_lat']) ? (float) $r['out_lat'] : null,
                        'lon' => isset($r['out_lon']) ? (float) $r['out_lon'] : null,
                        'runway' => isset($r['out_runway']) ? (float) $r['out_runway'] : null,
                        'penumpang' => isset($r['out_penumpang']) ? (int) $r['out_penumpang'] : null,
                        'rute_count' => (int) ($r['out_rute_count'] ?? 0),
                        'skor' => 0,
                        'jarak_km' => $distance,
                    ];
                }, $result);
            });

            $total = Cache::remember($cacheKey.'.count', 45, function () use ($pattern): int {
                $countQuery = 'SELECT (COUNT(DISTINCT ?a) AS ?total) WHERE { ?a rdfs:label ?nama . '.$pattern.' }';
                return (int) ($this->sparql->query($countQuery)[0]['total'] ?? 0);
            });

            if ($near !== null) {
                $rows = array_values(array_filter($rows, fn (array $r): bool => $r['jarak_km'] !== null && $r['jarak_km'] <= $radius));
                usort($rows, fn (array $a, array $b): int => ($a['jarak_km'] ?? PHP_FLOAT_MAX) <=> ($b['jarak_km'] ?? PHP_FLOAT_MAX));
                $total = count($rows);
                $rows = array_slice($rows, ($page - 1) * $perPage, $perPage);
            }
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
    private function searchTypoFallback(
        array $where,
        string $q,
        int $page,
        int $perPage,
        string $sort,
        ?array $near = null,
        int $radius = 100
    ): array
    {
        $pattern = implode("\n", $where);
        // Fallback juga harus mengembalikan satu baris per resource bandara.
        $query = 'SELECT ?a (SAMPLE(?iata) AS ?out_iata) (SAMPLE(?icao) AS ?out_icao) (SAMPLE(?nama) AS ?out_nama) (SAMPLE(?kota) AS ?out_kota) (SAMPLE(?wilayah) AS ?out_wilayah) (SAMPLE(?negara) AS ?out_negara) (SAMPLE(?kategori) AS ?out_kategori) (SAMPLE(?operator) AS ?out_operator) (SAMPLE(?asean) AS ?out_asean) (SAMPLE(?lat) AS ?out_lat) (SAMPLE(?lon) AS ?out_lon) (SAMPLE(?runway) AS ?out_runway) (SAMPLE(?penumpang) AS ?out_penumpang) (SAMPLE(?rute_count) AS ?out_rute_count) WHERE {
            ?a rdfs:label ?nama . '.$pattern.'
        } GROUP BY ?a ORDER BY LCASE(STR(?out_nama)) LIMIT 5000';

        try {
            $bindings = $this->sparql->query($query);
        } catch (\Throwable $e) {
            report($e);
            return [[], 0];
        }

        $needle = $this->normalizeTypoText($q);
        $tokens = preg_split('/\\s+/u', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ranked = [];

        foreach ($bindings as $binding) {
            $fields = [
                'nama' => $this->normalizeTypoText((string) ($binding['out_nama'] ?? '')),
                'iata' => $this->normalizeTypoText((string) ($binding['out_iata'] ?? '')),
                'icao' => $this->normalizeTypoText((string) ($binding['out_icao'] ?? '')),
                'kota' => $this->normalizeTypoText((string) ($binding['out_kota'] ?? '')),
                'wilayah' => $this->normalizeTypoText((string) ($binding['out_wilayah'] ?? '')),
                'negara' => $this->normalizeTypoText((string) ($binding['out_negara'] ?? '')),
            ];

            $score = 0;
            $allMatched = false;

            // Semua token harus cocok pada satu field lokasi/nama yang sama.
            // Ini mencegah "hai phong" mengambil "hai" dari Thailand dan
            // "phong" dari nama wilayah lain yang kebetulan mirip.
            foreach ($fields as $fieldName => $field) {
                if ($field === '') continue;
                $words = preg_split('/\s+/u', $field, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $tokenCount = count($tokens);
                $fieldScore = 0;

                // Kata pada query multi-kata harus cocok berurutan dan
                // berdampingan pada field yang sama. Ini mencegah "hai phong"
                // cocok ke "Thailand" + "Khlong" pada satu label wilayah.
                $windowCount = max(0, count($words) - $tokenCount + 1);
                for ($start = 0; $start < $windowCount; $start++) {
                    $windowScore = 0;
                    $windowMatched = true;

                    foreach ($tokens as $index => $token) {
                        $word = $words[$start + $index];
                        $tokenLength = mb_strlen($token);
                        $wordLength = mb_strlen($word);
                        $candidateScore = 0;

                        if ($word === $token) {
                            $candidateScore = 120;
                        } elseif (str_starts_with($word, $token)) {
                            $candidateScore = 105;
                        } elseif (str_contains($word, $token)) {
                            $candidateScore = 95;
                        } elseif ($tokenLength >= 4 && $wordLength > $tokenLength && $this->isSubsequence($token, $word)) {
                            $missing = $wordLength - $tokenLength;
                            $maxMissing = max(2, (int) floor($wordLength * 0.35));
                            if ($missing <= $maxMissing) {
                                $candidateScore = 85 - ($missing * 5);
                            }
                        }

                        if ($candidateScore === 0 && $tokenCount === 1 && $tokenLength >= 4) {
                            $distance = levenshtein($token, $word);
                            $maxLength = max($tokenLength, $wordLength);
                            $similarity = $maxLength > 0 ? (1 - ($distance / $maxLength)) : 0;
                            $allowed = match (true) {
                                $tokenLength >= 6 => 3,
                                $tokenLength >= 4 => 2,
                                default => 0,
                            };

                            if ($distance <= $allowed && $similarity >= 0.65) {
                                $candidateScore = 78 - ($distance * 12);
                            }
                        }

                        if ($candidateScore === 0) {
                            $windowMatched = false;
                            break;
                        }

                        $windowScore += $candidateScore;
                    }

                    if ($windowMatched) {
                        $fieldScore = max($fieldScore, $windowScore);
                    }
                }

                if ($fieldScore > 0) {
                    $weight = match ($fieldName) {
                        'nama', 'iata', 'icao' => 5,
                        'kota' => 4,
                        'wilayah' => 3,
                        'negara' => 2,
                        default => 1,
                    };
                    $fieldScore += $weight;

                    if (str_contains($field, $needle)) {
                        $fieldScore += match ($fieldName) {
                            'kota' => 35,
                            'wilayah' => 25,
                            'nama' => 20,
                            'negara' => 10,
                            default => 0,
                        };
                    }

                    $score = max($score, $fieldScore);
                    $allMatched = true;
                }
            }

            if (!$allMatched || $score <= 0) continue;

            $distance = null;
            if ($near !== null && isset($binding['out_lat'], $binding['out_lon'])) {
                $distance = Geo::jarakKm($near['lat'], $near['lon'], (float) $binding['out_lat'], (float) $binding['out_lon']);
                if ($distance > $radius) continue;
            } elseif ($near !== null) {
                continue;
            }

            $ranked[] = [
                'iata' => $binding['out_iata'] ?? null,
                'nama' => $binding['out_nama'] ?? '',
                'kota' => $binding['out_kota'] ?? null,
                'wilayah' => $binding['out_wilayah'] ?? ($binding['out_kota'] ?? null),
                'negara' => $binding['out_negara'] ?? null,
                'kategori' => $binding['out_kategori'] ?? null,
                'operator' => $binding['out_operator'] ?? null,
                'in_asean' => filter_var($binding['out_asean'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'lat' => isset($binding['out_lat']) ? (float) $binding['out_lat'] : null,
                'lon' => isset($binding['out_lon']) ? (float) $binding['out_lon'] : null,
                'runway' => isset($binding['out_runway']) ? (float) $binding['out_runway'] : null,
                'penumpang' => isset($binding['out_penumpang']) ? (int) $binding['out_penumpang'] : null,
                'rute_count' => (int) ($binding['out_rute_count'] ?? 0),
                'skor' => $score,
                'jarak_km' => $distance,
            ];
        }

        usort($ranked, function (array $a, array $b) use ($sort, $near): int {
            if ($near !== null && $a['jarak_km'] !== $b['jarak_km']) {
                return ($a['jarak_km'] ?? PHP_FLOAT_MAX) <=> ($b['jarak_km'] ?? PHP_FLOAT_MAX);
            }
            if ($a['skor'] !== $b['skor']) return $b['skor'] <=> $a['skor'];
            if ($sort === 'penumpang') return ($b['penumpang'] ?? 0) <=> ($a['penumpang'] ?? 0);
            return strcasecmp($a['nama'], $b['nama']);
        });

        $total = count($ranked);
        return [array_slice($ranked, ($page - 1) * $perPage, $perPage), $total];
    }

    /** True when each character in the query appears in order in the candidate. */
    private function isSubsequence(string $needle, string $haystack): bool
    {
        $needleChars = preg_split('//u', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $haystackChars = preg_split('//u', $haystack, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $position = 0;

        foreach ($haystackChars as $char) {
            if (($needleChars[$position] ?? null) === $char) {
                $position++;
                if ($position >= count($needleChars)) {
                    return true;
                }
            }
        }

        return $needleChars === [];
    }

    private function normalizeTypoText(string $text): string
    {
        $text = mb_strtolower(\Illuminate\Support\Str::ascii(trim($text)));
        $text = preg_replace('/[^\\p{L}\\p{N}]+/u', ' ', $text) ?? $text;
        return trim(preg_replace('/\\s+/u', ' ', $text) ?? $text);
    }
}
