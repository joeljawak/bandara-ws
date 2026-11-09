<?php

namespace App\Services;

use App\Support\TurtleParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mengubah graph Turtle menjadi baris relasional.
 *
 * Entity yang dikenali: Country, Region, Airport, Airline, Route. Nilai
 * properti diambil berdasarkan local name predikat sehingga namespace
 * http://example.org/bandara# dan http://example.org/asean-airport#
 * (termasuk tambahan_wikidata.ttl) dapat digabung.
 */
class AirportTtlImporter
{
    public function __construct(private TurtleParser $parser) {}

    /**
     * @param  list<string>  $paths
     * @return array{
     *     files: list<string>,
     *     triples: int,
     *     subjects: int,
     *     parsed: array<string, int>,
     *     stored: array<string, int>,
     *     skipped_routes: list<string>,
     *     duration: float
     * }
     */
    public function import(array $paths, bool $fresh = false): array
    {
        $paths = array_values(array_unique(array_filter($paths)));

        if ($paths === []) {
            throw new InvalidArgumentException('Minimal satu file Turtle diperlukan.');
        }

        $startedAt = microtime(true);
        $triples = [];

        foreach ($paths as $path) {
            foreach ($this->parser->parseFile($path) as $triple) {
                $triples[] = $triple;
            }
        }

        $entities = $this->groupEntities($triples);
        $data = $this->buildEntities($entities);

        if ($fresh) {
            $this->deleteExistingRows();
        }

        $skippedRoutes = [];

        DB::transaction(function () use ($data, &$skippedRoutes): void {
            $skippedRoutes = $this->persist($data);
        });

        return [
            'files' => $paths,
            'triples' => count($triples),
            'subjects' => count(array_unique(array_column($triples, 'subject'))),
            'parsed' => $data['parsed'],
            'stored' => [
                'countries' => DB::table('countries')->count(),
                'regions' => DB::table('regions')->count(),
                'airports' => DB::table('airports')->count(),
                'airlines' => DB::table('airlines')->count(),
                'routes' => DB::table('routes')->count(),
                'airline_hub' => DB::table('airline_hub')->count(),
                'airline_airport' => DB::table('airline_airport')->count(),
            ],
            'skipped_routes' => $skippedRoutes,
            'duration' => round(microtime(true) - $startedAt, 2),
        ];
    }

    /**
     * @param  list<array{
     *     subject: string,
     *     predicate: string,
     *     object: string,
     *     object_type: 'iri'|'literal'|'bnode',
     *     datatype: string|null,
     *     language: string|null
     * }>  $triples
     * @return array<string, array<string, array<string, list<array{value: string, type: string, datatype: string|null, language: string|null}>>>>
     */
    private function groupEntities(array $triples): array
    {
        $grouped = [];

        foreach ($triples as $triple) {
            $grouped[$triple['subject']][] = $triple;
        }

        $entities = [
            'country' => [],
            'region' => [],
            'airport' => [],
            'airline' => [],
            'route' => [],
        ];

        foreach ($grouped as $subject => $subjectTriples) {
            $type = $this->entityType($subject, $subjectTriples);

            if ($type !== null) {
                $entities[$type][$subject] = $this->propertyMap($subjectTriples);
            }
        }

        return $entities;
    }

    /**
     * @param  list<array{
     *     subject: string,
     *     predicate: string,
     *     object: string,
     *     object_type: 'iri'|'literal'|'bnode',
     *     datatype: string|null,
     *     language: string|null
     * }>  $triples
     * @return array<string, list<array{value: string, type: string, datatype: string|null, language: string|null}>>
     */
    private function propertyMap(array $triples): array
    {
        $properties = [];

        foreach ($triples as $triple) {
            $properties[$this->localName($triple['predicate'])][] = [
                'value' => $triple['object'],
                'type' => $triple['object_type'],
                'datatype' => $triple['datatype'],
                'language' => $triple['language'],
            ];
        }

        return $properties;
    }

    /**
     * @param  list<array{subject: string, predicate: string, object: string, object_type: string, datatype: string|null, language: string|null}>  $triples
     */
    private function entityType(string $subject, array $triples): ?string
    {
        $types = [
            'Airport' => 'airport',
            'Airline' => 'airline',
            'Route' => 'route',
            'Country' => 'country',
            'Region' => 'region',
        ];

        foreach ($triples as $triple) {
            if ($triple['predicate'] !== TurtleParser::RDF_TYPE || $triple['object_type'] !== 'iri') {
                continue;
            }

            $local = $this->localName($triple['object']);

            if (isset($types[$local])) {
                return $types[$local];
            }
        }

        $local = strtolower($this->localName($subject));

        foreach (['airport', 'airline', 'route', 'country', 'region'] as $prefix) {
            if (str_starts_with($local, $prefix.'_')) {
                return $prefix;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<string, array<string, list<array{value: string, type: string, datatype: string|null, language: string|null}>>>>  $entities
     * @return array{
     *     countries: array<string, array<string, mixed>>,
     *     regions: array<string, array<string, mixed>>,
     *     airports: array<string, array<string, mixed>>,
     *     airlines: array<string, array<string, mixed>>,
     *     routes: array<string, array<string, mixed>>,
     *     parsed: array<string, int>
     * }
     */
    private function buildEntities(array $entities): array
    {
        $countries = [];
        $regions = [];
        $airports = [];
        $airlines = [];
        $routes = [];

        $parsed = [
            'countries' => count($entities['country']),
            'regions' => count($entities['region']),
            'airports' => count($entities['airport']),
            'airlines' => count($entities['airline']),
            'routes' => count($entities['route']),
        ];

        foreach ($entities['country'] as $subject => $properties) {
            $id = $this->externalId($subject);
            $countries[$id] = [
                'external_id' => $id,
                'label' => $this->literal($properties, ['label']) ?? $this->humanize($id),
                'member_of_asean' => $this->boolean($properties, ['memberOfASEAN']),
            ];
        }

        foreach ($entities['region'] as $subject => $properties) {
            $id = $this->externalId($subject);
            $regions[$id] = [
                'external_id' => $id,
                'label' => $this->literal($properties, ['label']) ?? $this->humanize($id),
                'admin_level' => $this->integer($properties, ['adminLevel']),
                '_country' => $this->iriLocal($properties, ['locatedInCountry', 'country']),
                '_parent' => $this->iriLocal($properties, ['partOfRegion', 'parentRegion']),
            ];
        }

        foreach ($entities['airport'] as $subject => $properties) {
            $id = $this->externalId($subject);
            $airports[$id] = [
                'external_id' => $id,
                'label' => $this->literal($properties, ['label']) ?? $this->humanize($id),
                'iata_code' => $this->code($this->literal($properties, ['hasIATACode', 'iataCode', 'iata', 'kodeIATA'])),
                'icao_code' => $this->code($this->literal($properties, ['hasICAOCode', 'icaoCode', 'icao', 'kodeICAO'])),
                'category' => $this->literal($properties, ['airportCategory', 'category', 'kategori']),
                'status' => $this->literal($properties, ['airportStatus', 'status']),
                'latitude' => $this->float($properties, ['lat', 'latitude']),
                'longitude' => $this->float($properties, ['long', 'lon', 'longitude']),
                'elevation' => $this->float($properties, ['elevation']),
                '_country' => $this->iriLocal($properties, ['locatedInCountry', 'country']),
                '_region' => $this->iriLocal($properties, ['locatedInRegion', 'region']),
                'in_asean' => $this->boolean($properties, ['inASEAN', 'inAsean']),
                '_nearest' => $this->iriLocal($properties, ['nearestAirport']),
                'distance_to_city_center_km' => $this->float($properties, ['distanceToCityCenterKm']),
                'distance_to_nearest_airport_km' => $this->float($properties, ['distanceToNearestAirportKm']),
                'longest_runway_length' => $this->float($properties, ['longestRunwayLength']),
                'longest_runway_width' => $this->float($properties, ['longestRunwayWidth']),
                'runway_count' => $this->integer($properties, ['runwayCount']),
                'runway_designation' => $this->literal($properties, ['runwayDesignation']),
                'surface_material' => $this->literal($properties, ['surfaceMaterial']),
                'operator' => $this->literal($properties, ['operator', 'pengelola']),
                'annual_passengers' => $this->integer($properties, ['annualPassengers', 'penumpang']),
                'peak_annual_passengers' => $this->integer($properties, ['peakAnnualPassengers']),
                'number_of_airlines' => $this->integer($properties, ['numberOfAirlines']),
                'number_of_destinations' => $this->integer($properties, ['numberOfDestinations']),
                'number_of_destinations_asean' => $this->integer($properties, ['numberOfDestinationsASEAN']),
                'number_of_destinations_outside_asean' => $this->integer($properties, ['numberOfDestinationsOutsideASEAN']),
                'local_name' => $this->literal($properties, ['localName']),
                'website' => $this->literal($properties, ['website']) ?? $this->iri($properties, ['website']),
                'opened_year' => $this->integer($properties, ['openedYear']),
                'dbpedia_url' => $this->iri($properties, ['sameAs']),
                'wikidata_url' => $this->iri($properties, ['seeAlso']),
            ];
        }

        foreach ($entities['airline'] as $subject => $properties) {
            $id = $this->externalId($subject);
            $airlines[$id] = [
                'external_id' => $id,
                'label' => $this->literal($properties, ['label']) ?? $this->humanize($id),
                'airline_code' => $this->code($this->literal($properties, ['airlineCode', 'iataCode', 'code'])),
                'website' => $this->literal($properties, ['website']) ?? $this->iri($properties, ['website']),
                'dbpedia_url' => $this->iri($properties, ['sameAs']),
                'wikidata_url' => $this->iri($properties, ['seeAlso']),
                '_hubs' => $this->iriLocalList($properties, ['hubAt', 'hubAirport']),
                '_operates_at' => $this->iriLocalList($properties, ['operatesAt', 'servesAirport']),
            ];
        }

        foreach ($entities['route'] as $subject => $properties) {
            $id = $this->externalId($subject);
            $routes[$id] = [
                'external_id' => $id,
                '_origin' => $this->iriLocal($properties, ['origin', 'from']),
                '_destination' => $this->iriLocal($properties, ['destination', 'to']),
                '_airline' => $this->iriLocal($properties, ['operatedBy', 'airline']),
            ];
        }

        $this->ensureReferences($countries, $regions, $airports, $airlines, $routes);

        return compact('countries', 'regions', 'airports', 'airlines', 'routes', 'parsed');
    }

    /**
     * @param  array<string, array<string, mixed>>  $countries
     * @param  array<string, array<string, mixed>>  $regions
     * @param  array<string, array<string, mixed>>  $airports
     * @param  array<string, array<string, mixed>>  $airlines
     * @param  array<string, array<string, mixed>>  $routes
     */
    private function ensureReferences(array &$countries, array &$regions, array &$airports, array &$airlines, array $routes): void
    {
        foreach ($regions as $region) {
            $this->ensureCountry($countries, $region['_country']);
            $this->ensureRegion($regions, $region['_parent']);
        }

        foreach ($airports as $airport) {
            $this->ensureCountry($countries, $airport['_country']);
            $this->ensureRegion($regions, $airport['_region']);
            $this->ensureAirport($airports, $airport['_nearest']);
        }

        foreach ($airlines as $airline) {
            foreach (array_merge($airline['_hubs'], $airline['_operates_at']) as $airportId) {
                $this->ensureAirport($airports, $airportId);
            }
        }

        foreach ($routes as $route) {
            $this->ensureAirport($airports, $route['_origin']);
            $this->ensureAirport($airports, $route['_destination']);
            $this->ensureAirline($airlines, $route['_airline']);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $countries
     */
    private function ensureCountry(array &$countries, ?string $id): void
    {
        if ($id === null || $id === '' || isset($countries[$id])) {
            return;
        }

        $countries[$id] = [
            'external_id' => $id,
            'label' => $this->humanize($id),
            'member_of_asean' => false,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $regions
     */
    private function ensureRegion(array &$regions, ?string $id): void
    {
        if ($id === null || $id === '' || isset($regions[$id])) {
            return;
        }

        $regions[$id] = [
            'external_id' => $id,
            'label' => $this->humanize($id),
            'admin_level' => null,
            '_country' => null,
            '_parent' => null,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $airports
     */
    private function ensureAirport(array &$airports, ?string $id): void
    {
        if ($id === null || $id === '' || isset($airports[$id])) {
            return;
        }

        $airports[$id] = [
            'external_id' => $id,
            'label' => $this->humanize($id),
            'iata_code' => null,
            'icao_code' => null,
            'category' => null,
            'status' => null,
            'latitude' => null,
            'longitude' => null,
            'elevation' => null,
            '_country' => null,
            '_region' => null,
            'in_asean' => false,
            '_nearest' => null,
            'distance_to_city_center_km' => null,
            'distance_to_nearest_airport_km' => null,
            'longest_runway_length' => null,
            'longest_runway_width' => null,
            'runway_count' => null,
            'runway_designation' => null,
            'surface_material' => null,
            'operator' => null,
            'annual_passengers' => null,
            'peak_annual_passengers' => null,
            'number_of_airlines' => null,
            'number_of_destinations' => null,
            'number_of_destinations_asean' => null,
            'number_of_destinations_outside_asean' => null,
            'local_name' => null,
            'website' => null,
            'opened_year' => null,
            'dbpedia_url' => null,
            'wikidata_url' => null,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $airlines
     */
    private function ensureAirline(array &$airlines, ?string $id): void
    {
        if ($id === null || $id === '' || isset($airlines[$id])) {
            return;
        }

        $airlines[$id] = [
            'external_id' => $id,
            'label' => $this->humanize($id),
            'airline_code' => null,
            'website' => null,
            'dbpedia_url' => null,
            'wikidata_url' => null,
            '_hubs' => [],
            '_operates_at' => [],
        ];
    }

    /**
     * @param  array{
     *     countries: array<string, array<string, mixed>>,
     *     regions: array<string, array<string, mixed>>,
     *     airports: array<string, array<string, mixed>>,
     *     airlines: array<string, array<string, mixed>>,
     *     routes: array<string, array<string, mixed>>,
     *     parsed: array<string, int>
     * }  $data
     * @return list<string>
     */
    private function persist(array $data): array
    {
        $now = now();

        $countryRows = array_map(
            fn (array $row): array => $this->stamp($this->withoutTemporary($row), $now),
            array_values($data['countries'])
        );
        $this->upsert('countries', $countryRows, ['external_id']);
        $countryIds = $this->idMap('countries');

        $regionRows = [];

        foreach ($data['regions'] as $row) {
            $regionRows[] = $this->stamp(array_merge($this->withoutTemporary($row), [
                'country_id' => $row['_country'] !== null ? ($countryIds[$row['_country']] ?? null) : null,
                'parent_id' => null,
            ]), $now);
        }

        $this->upsert('regions', $regionRows, ['external_id']);
        $regionIds = $this->idMap('regions');

        foreach ($data['regions'] as $id => $row) {
            if ($row['_parent'] === null || ! isset($regionIds[$id])) {
                continue;
            }

            DB::table('regions')
                ->where('id', $regionIds[$id])
                ->update([
                    'parent_id' => $regionIds[$row['_parent']] ?? null,
                    'updated_at' => $now,
                ]);
        }

        $airportRows = [];

        foreach ($data['airports'] as $row) {
            $airportRows[] = $this->stamp(array_merge($this->withoutTemporary($row), [
                'country_id' => $row['_country'] !== null ? ($countryIds[$row['_country']] ?? null) : null,
                'region_id' => $row['_region'] !== null ? ($regionIds[$row['_region']] ?? null) : null,
                'nearest_airport_id' => null,
            ]), $now);
        }

        $this->upsert('airports', $airportRows, ['external_id']);
        $airportIds = $this->idMap('airports');

        foreach ($data['airports'] as $id => $row) {
            if ($row['_nearest'] === null || ! isset($airportIds[$id])) {
                continue;
            }

            DB::table('airports')
                ->where('id', $airportIds[$id])
                ->update([
                    'nearest_airport_id' => $airportIds[$row['_nearest']] ?? null,
                    'updated_at' => $now,
                ]);
        }

        $airlineRows = [];

        foreach ($data['airlines'] as $row) {
            $firstHub = $row['_hubs'][0] ?? null;

            $airlineRows[] = $this->stamp(array_merge($this->withoutTemporary($row), [
                'hub_airport_id' => $firstHub !== null ? ($airportIds[$firstHub] ?? null) : null,
            ]), $now);
        }

        $this->upsert('airlines', $airlineRows, ['external_id']);
        $airlineIds = $this->idMap('airlines');

        $hubs = [];
        $servedAirports = [];

        foreach ($data['airlines'] as $id => $row) {
            $airlineId = $airlineIds[$id] ?? null;

            if ($airlineId === null) {
                continue;
            }

            foreach ($row['_hubs'] as $airportExternalId) {
                $airportId = $airportIds[$airportExternalId] ?? null;

                if ($airportId !== null) {
                    $hubs[$airlineId.'|'.$airportId] = [
                        'airline_id' => $airlineId,
                        'airport_id' => $airportId,
                    ];
                }
            }

            foreach ($row['_operates_at'] as $airportExternalId) {
                $airportId = $airportIds[$airportExternalId] ?? null;

                if ($airportId !== null) {
                    $servedAirports[$airlineId.'|'.$airportId] = [
                        'airline_id' => $airlineId,
                        'airport_id' => $airportId,
                    ];
                }
            }
        }

        $this->insertOrIgnore('airline_hub', array_values($hubs));
        $this->insertOrIgnore('airline_airport', array_values($servedAirports));

        $routeRows = [];
        $skippedRoutes = [];

        foreach ($data['routes'] as $id => $row) {
            $originId = $row['_origin'] !== null ? ($airportIds[$row['_origin']] ?? null) : null;
            $destinationId = $row['_destination'] !== null ? ($airportIds[$row['_destination']] ?? null) : null;
            $airlineId = $row['_airline'] !== null ? ($airlineIds[$row['_airline']] ?? null) : null;

            if ($originId === null || $destinationId === null || $airlineId === null) {
                $skippedRoutes[] = $id;

                continue;
            }

            $routeRows[] = $this->stamp([
                'external_id' => $id,
                'origin_airport_id' => $originId,
                'destination_airport_id' => $destinationId,
                'airline_id' => $airlineId,
            ], $now);
        }

        $this->upsert('routes', $routeRows, ['external_id']);

        return $skippedRoutes;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withoutTemporary(array $row): array
    {
        return Arr::except($row, array_values(array_filter(
            array_keys($row),
            fn (string $key): bool => str_starts_with($key, '_')
        )));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function stamp(array $row, mixed $now): array
    {
        return $row + [
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $uniqueBy
     */
    private function upsert(string $table, array $rows, array $uniqueBy): void
    {
        if ($rows === []) {
            return;
        }

        $updateColumns = array_values(array_diff(
            array_keys($rows[0]),
            array_merge($uniqueBy, ['created_at'])
        ));

        foreach (array_chunk($rows, 30) as $chunk) {
            DB::table($table)->upsert($chunk, $uniqueBy, $updateColumns);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertOrIgnore(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table($table)->insertOrIgnore($chunk);
        }
    }

    /**
     * @return array<string, int>
     */
    private function idMap(string $table): array
    {
        return DB::table($table)->pluck('id', 'external_id')->all();
    }

    private function deleteExistingRows(): void
    {
        foreach ([
            'routes',
            'airline_airport',
            'airline_hub',
            'airlines',
            'airports',
            'regions',
            'countries',
        ] as $table) {
            DB::table($table)->delete();
        }
    }

    /**
     * @param  array<string, list<array{value: string, type: string, datatype: string|null, language: string|null}>>  $properties
     * @param  list<string>  $names
     * @return array{value: string, type: string, datatype: string|null, language: string|null}|null
     */
    private function first(array $properties, array $names, ?string $type = null): ?array
    {
        foreach ($names as $name) {
            foreach ($properties[$name] ?? [] as $value) {
                if ($type === null || $value['type'] === $type) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<array{value: string, type: string, datatype: string|null, language: string|null}>>  $properties
     * @param  list<string>  $names
     */
    private function literal(array $properties, array $names): ?string
    {
        return $this->first($properties, $names, 'literal')['value'] ?? null;
    }

    /**
     * @param  array<string, list<array{value: string, type: string, datatype: string|null, language: string|null}>>  $properties
     * @param  list<string>  $names
     */
    private function iri(array $properties, array $names): ?string
    {
        return $this->first($properties, $names, 'iri')['value'] ?? null;
    }

    /**
     * @param  array<string, list<array{value: string, type: string, datatype: string|null, language: string|null}>>  $properties
     * @param  list<string>  $names
     */
    private function iriLocal(array $properties, array $names): ?string
    {
        $iri = $this->iri($properties, $names);

        return $iri === null ? null : $this->externalId($iri);
    }

    /**
     * @param  array<string, list<array{value: string, type: string, datatype: string|null, language: string|null}>>  $properties
     * @param  list<string>  $names
     * @return list<string>
     */
    private function iriLocalList(array $properties, array $names): array
    {
        $result = [];

        foreach ($names as $name) {
            foreach ($properties[$name] ?? [] as $value) {
                if ($value['type'] === 'iri') {
                    $id = $this->externalId($value['value']);
                    $result[$id] = $id;
                }
            }
        }

        return array_values($result);
    }

    /**
     * @param  array<string, list<array{value: string, type: string, datatype: string|null, language: string|null}>>  $properties
     * @param  list<string>  $names
     */
    private function boolean(array $properties, array $names, bool $default = false): bool
    {
        $value = $this->literal($properties, $names);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['true', '1', 'yes'], true);
    }

    /**
     * @param  array<string, list<array{value: string, type: string, datatype: string|null, language: string|null}>>  $properties
     * @param  list<string>  $names
     */
    private function float(array $properties, array $names): ?float
    {
        $value = $this->literal($properties, $names);

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param  array<string, list<array{value: string, type: string, datatype: string|null, language: string|null}>>  $properties
     * @param  list<string>  $names
     */
    private function integer(array $properties, array $names): ?int
    {
        $value = $this->float($properties, $names);

        return $value === null ? null : (int) round($value);
    }

    private function code(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return strtoupper(trim($value));
    }

    private function externalId(string $subject): string
    {
        $local = $this->localName($subject);

        return $local !== '' ? $local : $subject;
    }

    private function localName(string $iri): string
    {
        $hash = strrpos($iri, '#');
        $slash = strrpos($iri, '/');
        $position = max($hash === false ? -1 : $hash, $slash === false ? -1 : $slash);

        return $position === -1 ? $iri : substr($iri, $position + 1);
    }

    private function humanize(string $externalId): string
    {
        $label = preg_replace('/^(airport|airline|route|country|region)_/i', '', $externalId) ?? $externalId;
        $label = trim(str_replace('_', ' ', $label));

        return $label !== '' ? $label : $externalId;
    }
}
