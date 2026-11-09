<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SparqlService
{
    const PREFIX = "PREFIX ex: <http://example.org/bandara#>\n"
        . "PREFIX owl: <http://www.w3.org/2002/07/owl#>\n"
        . "PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>\n";

    public function query(string $sparql): array
    {
        $endpoint = config('sparql.endpoint');
        $res = Http::timeout(20)
            ->withHeaders(['Accept' => 'application/sparql-results+json'])
            ->asForm()
            ->post($endpoint, ['query' => self::PREFIX . $sparql]);

        if (! $res->successful()) {
            throw new \RuntimeException('Fuseki HTTP ' . $res->status() . ': ' . $res->body());
        }

        return collect($res->json('results.bindings') ?? [])->map(function ($row) {
            return collect($row)->map(fn ($v) => $v['value'] ?? null)->all();
        })->all();
    }

    public function esc(string $s): string
    {
        return str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', ' ', ' '], $s);
    }

    public function search(string $q): array
    {
        $q = $this->esc(mb_strtolower(trim($q)));
        $qCompact = $this->esc(preg_replace('/[\s-]+/u', '', mb_strtolower(trim($q))) ?? '');

        return $this->query('SELECT ?nama ?iata ?icao ?kota ?negara ?lat ?lon WHERE {
            ?a a ex:Bandara ; ex:namaBandara ?nama ; ex:kodeIATA ?iata ; ex:negara ?negara .
            OPTIONAL { ?a ex:kodeICAO ?icao }
            OPTIONAL { ?a ex:terletakDi ?city . ?city ex:namaKota ?kota }
            OPTIONAL { ?a ex:latitude ?lat }
            OPTIONAL { ?a ex:longitude ?lon }
            FILTER(
                CONTAINS(LCASE(STR(?nama)), "'.$q.'") ||
                LCASE(STR(?iata)) = "'.$q.'" ||
                (BOUND(?icao) && LCASE(STR(?icao)) = "'.$q.'") ||
                (BOUND(?kota) && CONTAINS(LCASE(STR(?kota)), "'.$q.'")) ||
                CONTAINS(LCASE(STR(?negara)), "'.$q.'") ||
                CONTAINS(REPLACE(LCASE(STR(?nama)), "[ -]", ""), "'.$qCompact.'")
            )
        }
        ORDER BY ASC(IF(LCASE(STR(?iata)) = "'.$q.'", 0, IF(CONTAINS(LCASE(STR(?nama)), "'.$q.'"), 1, 2))) ?nama
        LIMIT 50');
    }

    public function allWithCoords(): array
    {
        return $this->query('SELECT DISTINCT ?nama ?iata ?lat ?lon WHERE {
            ?a a ex:Bandara ; ex:namaBandara ?nama ; ex:kodeIATA ?iata ;
               ex:latitude ?lat ; ex:longitude ?lon .
        } ORDER BY ?nama');
    }
}
