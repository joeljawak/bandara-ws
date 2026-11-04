<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FusekiService
{
    const PREFIX = "PREFIX ex: <http://example.org/asean-airport#>\n"
        . "PREFIX geo1: <http://www.w3.org/2003/01/geo/wgs84_pos#>\n"
        . "PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>\n"
        . "PREFIX owl: <http://www.w3.org/2002/07/owl#>\n";

    // kirim query ke Fuseki, hasilnya jadi array biasa
    public function query(string $sparql): array
    {
        $res = Http::withHeaders(['Accept' => 'application/sparql-results+json'])
            ->asForm()
            ->timeout(15)
            ->post(config('services.fuseki.url'), ['query' => self::PREFIX . $sparql]);

        $res->throw();

        $rows = [];
        foreach ($res->json('results.bindings') ?? [] as $binding) {
            $row = [];
            foreach ($binding as $key => $val) {
                $row[$key] = $val['value'];
            }
            $rows[] = $row;
        }

        return $rows;
    }

    // bersihkan teks dari user sebelum masuk query
    public static function esc(string $text): string
    {
        $text = str_replace(["\r", "\n"], ' ', $text);

        return addcslashes($text, '\\"');
    }
}
