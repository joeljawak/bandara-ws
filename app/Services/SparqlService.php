<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SparqlService
{
    const PREFIX = "PREFIX ex: <http://example.org/bandara#>\nPREFIX owl: <http://www.w3.org/2002/07/owl#>\n";

    // kirim query ke Fuseki, hasilnya array baris
    public function query(string $sparql): array
    {
        $res = Http::timeout(15)
            ->withHeaders(['Accept' => 'application/sparql-results+json'])
            ->asForm()
            ->post(config('sparql.endpoint'), ['query' => self::PREFIX . $sparql]);

        if (!$res->successful()) {
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

    public function search(string $q): array
    {
        $q = $this->esc(mb_strtolower($q));
        return $this->query('SELECT ?nama ?iata ?icao ?kota WHERE {
            ?a a ex:Bandara ; ex:namaBandara ?nama ; ex:kodeIATA ?iata ; ex:terletakDi ?k .
            OPTIONAL { ?a ex:kodeICAO ?icao }
            ?k ex:namaKota ?kota .
            FILTER(CONTAINS(LCASE(?nama), "' . $q . '") || CONTAINS(LCASE(?kota), "' . $q . '") || LCASE(?iata) = "' . $q . '")
        } ORDER BY ?nama LIMIT 50');
    }

    public function find(string $iata): ?array
    {
        $iata = $this->esc(strtoupper($iata));
        $rows = $this->query('SELECT ?nama ?iata ?icao ?kota ?lat ?lon ?dbpedia WHERE {
            ?a a ex:Bandara ; ex:namaBandara ?nama ; ex:kodeIATA ?iata ; ex:terletakDi ?k .
            FILTER(?iata = "' . $iata . '")
            ?k ex:namaKota ?kota .
            OPTIONAL { ?a ex:kodeICAO ?icao }
            OPTIONAL { ?a ex:latitude ?lat . ?a ex:longitude ?lon }
            OPTIONAL { ?a owl:sameAs ?dbpedia }
        } LIMIT 1');
        return $rows[0] ?? null;
    }

    // daftar semua bandara yang punya koordinat, dipakai untuk dropdown halaman rute
    public function allWithCoords(): array
    {
        return $this->query('SELECT ?nama ?iata ?lat ?lon WHERE {
            ?a a ex:Bandara ; ex:namaBandara ?nama ; ex:kodeIATA ?iata ;
               ex:latitude ?lat ; ex:longitude ?lon .
        } ORDER BY ?nama');
    }

    public function airlines(string $iata): array
    {
        $iata = $this->esc(strtoupper($iata));
        return $this->query('SELECT DISTINCT ?maskapai WHERE {
            ?a ex:kodeIATA "' . $iata . '" ; ex:dilayaniOleh ?m .
            ?m ex:namaMaskapai ?maskapai .
        } ORDER BY ?maskapai');
    }
}
