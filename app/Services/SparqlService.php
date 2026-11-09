<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SparqlService
{
    const PREFIX = "PREFIX ex: <http://example.org/asean-airport#>\n"
        . "PREFIX geo1: <http://www.w3.org/2003/01/geo/wgs84_pos#>\n"
        . "PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>\n"
        . "PREFIX owl: <http://www.w3.org/2002/07/owl#>\n";

    // kirim query ke Fuseki, hasilnya array baris
    public function query(string $sparql): array
    {
        $res = Http::timeout(15)
            ->withHeaders(['Accept' => 'application/sparql-results+json'])
            ->asForm()
            ->post(config('sparql.endpoint'), ['query' => self::PREFIX.$sparql]);

        if (! $res->successful()) {
            throw new \RuntimeException('SPARQL endpoint error');
        }

        return collect($res->json('results.bindings'))->map(function ($row) {
            return collect($row)->map(fn ($v) => $v['value'])->all();
        })->all();
    }

    // bersihkan input supaya aman masuk ke string SPARQL
    public function esc(string $s): string
    {
        return str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', ' ', ' '], $s);
    }

    // data tambahan (negara, koordinat, landasan, penumpang) dipakai untuk gambar bandara
    public function search(string $q): array
    {
        $q = $this->esc(preg_replace('/[\s-]+/u', '', mb_strtolower(trim($q))) ?? '');

        return $this->query('SELECT ?nama ?iata ?icao ?kota ?negara ?lat ?lon ?runway ?penumpang WHERE {
            ?a a <http://example.org/asean-airport#Airport> ;
               <http://www.w3.org/2000/01/rdf-schema#label> ?nama ;
               <http://example.org/asean-airport#hasIATACode> ?iata .
            OPTIONAL { ?a <http://example.org/asean-airport#hasICAOCode> ?icao }
            OPTIONAL {
                ?a <http://example.org/asean-airport#locatedInRegion> ?region .
                ?region <http://www.w3.org/2000/01/rdf-schema#label> ?kota .
                OPTIONAL {
                    ?region <http://example.org/asean-airport#partOfRegion> ?induk .
                    ?induk <http://www.w3.org/2000/01/rdf-schema#label> ?provinsi .
                }
            }
            OPTIONAL {
                ?a <http://example.org/asean-airport#locatedInCountry> ?country .
                ?country <http://www.w3.org/2000/01/rdf-schema#label> ?negara .
            }
            OPTIONAL { ?a geo1:lat ?lat ; geo1:long ?lon }
            OPTIONAL { ?a <http://example.org/asean-airport#longestRunwayLength> ?runway }
            OPTIONAL { ?a <http://example.org/asean-airport#annualPassengers> ?penumpang }
            FILTER(
                CONTAINS(REPLACE(LCASE(STR(?nama)), "[ -]", ""), "'.$q.'") ||
                LCASE(STR(?iata)) = "'.$q.'" ||
                (BOUND(?icao) && LCASE(STR(?icao)) = "'.$q.'") ||
                (BOUND(?kota) && CONTAINS(REPLACE(LCASE(STR(?kota)), "[ -]", ""), "'.$q.'")) ||
                (BOUND(?provinsi) && CONTAINS(REPLACE(LCASE(STR(?provinsi)), "[ -]", ""), "'.$q.'")) ||
                (BOUND(?negara) && CONTAINS(REPLACE(LCASE(STR(?negara)), "[ -]", ""), "'.$q.'"))
            )
        } ORDER BY
            ASC(IF(LCASE(STR(?iata)) = "'.$q.'", 0, IF(STRSTARTS(REPLACE(LCASE(STR(?nama)), "[ -]", ""), "'.$q.'"), 1, 2)))
            ?nama
        LIMIT 50');
    }

    // daftar bandara yang punya koordinat, dipakai untuk dropdown halaman rute
    public function allWithCoords(): array
    {
        return $this->query('SELECT DISTINCT ?nama ?iata ?lat ?lon WHERE {
            ?a a ex:Airport ; rdfs:label ?nama ; ex:hasIATACode ?iata ;
               geo1:lat ?lat ; geo1:long ?lon .
        } ORDER BY ?nama');
    }
}
