<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FusekiService
{
    // Namespace dan properti mengikuti bandara_asean.ttl
    const PREFIX = "PREFIX ex: <http://example.org/bandara#>\n"
        . "PREFIX owl: <http://www.w3.org/2002/07/owl#>\n"
        . "PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>\n";

    public function query(string $sparql): array
    {
        $endpoint = config('services.fuseki.url');
        $res = Http::withHeaders(['Accept' => 'application/sparql-results+json'])
            ->asForm()
            ->timeout(20)
            ->post($endpoint, ['query' => self::PREFIX . $sparql]);

        if (! $res->successful()) {
            throw new \RuntimeException('Fuseki HTTP ' . $res->status() . ': ' . $res->body());
        }

        $rows = [];
        foreach ($res->json('results.bindings') ?? [] as $binding) {
            $row = [];
            foreach ($binding as $key => $val) {
                $row[$key] = $val['value'] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public static function esc(string $text): string
    {
        return str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', ' ', ' '], $text);
    }
}
