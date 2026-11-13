<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Klien tipis untuk SPARQL endpoint Fuseki.
 *
 * Satu-satunya konfigurasi ada di config/sparql.php. Vocabulary kanonik adalah
 * http://example.org/asean-airport#; vocabulary lama bandara# hanya dipakai
 * saat membaca berkas mentah atau endpoint lama.
 */
class SparqlService
{
    public const VOCABULARY = 'http://example.org/asean-airport#';

    public const LEGACY_VOCABULARY = 'http://example.org/bandara#';

    public const RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    public const RDFS = 'http://www.w3.org/2000/01/rdf-schema#';

    /**
     * @return list<array<string, string|null>>
     */
    public function query(string $sparql): array
    {
        $res = Http::timeout((int) config('sparql.timeout', 15))
            ->withHeaders(['Accept' => 'application/sparql-results+json'])
            ->asForm()
            ->post(config('sparql.endpoint'), ['query' => $this->prefix().$sparql]);

        if (! $res->successful()) {
            throw new \RuntimeException('Fuseki HTTP '.$res->status().': '.$res->body());
        }

        return collect($res->json('results.bindings') ?? [])
            ->map(fn (array $row): array => collect($row)
                ->map(fn (array $value): ?string => $value['value'] ?? null)
                ->all())
            ->values()
            ->all();
    }

    public function prefix(): string
    {
        return 'PREFIX ex: <'.config('sparql.vocabulary', self::VOCABULARY).">\n"
            .'PREFIX rdf: <'.self::RDF.">\n"
            .'PREFIX rdfs: <'.self::RDFS.">\n"
            .'PREFIX owl: <http://www.w3.org/2002/07/owl#>'."\n"
            .'PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>'."\n";
    }

    /**
     * Escape literal string sebelum dimasukkan ke query SPARQL.
     */
    public static function esc(string $teks): string
    {
        return str_replace(
            ['\\', '"', "\r", "\n", "\t"],
            ['\\\\', '\\"', ' ', ' ', '\\t'],
            $teks
        );
    }
}
