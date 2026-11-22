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

    /**
     * Alamat dataset Fuseki, misalnya http://localhost:3030/bandara.
     */
    public function datasetUrl(): string
    {
        $dataset = config('sparql.dataset_url');

        if (is_string($dataset) && trim($dataset) !== '') {
            return rtrim(trim($dataset), '/');
        }

        $endpoint = (string) config('sparql.endpoint');

        return rtrim((string) preg_replace('#/(sparql|query)/?$#', '', $endpoint), '/');
    }

    /**
     * Cek apakah Fuseki bisa dihubungi dan berapa triple yang tersimpan.
     *
     * @return array{ok: bool, triples: int|null, error: string|null}
     */
    public function status(): array
    {
        try {
            $rows = $this->query('SELECT (COUNT(*) AS ?n) WHERE { ?s ?p ?o }');

            return ['ok' => true, 'triples' => (int) ($rows[0]['n'] ?? 0), 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'triples' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Ambil seluruh triple dari Fuseki lewat SPARQL CONSTRUCT, dalam bentuk N-Triples.
     *
     * @return array{body: string, version: string}
     */
    public function constructAll(): array
    {
        $res = Http::timeout((int) config('sparql.dump_timeout', 120))
            ->withHeaders(['Accept' => 'application/n-triples'])
            ->asForm()
            ->post(config('sparql.endpoint'), ['query' => 'CONSTRUCT { ?s ?p ?o } WHERE { ?s ?p ?o }']);

        if (! $res->successful()) {
            throw new \RuntimeException('Fuseki HTTP '.$res->status().': '.mb_substr($res->body(), 0, 300));
        }

        $body = $res->body();

        return ['body' => $body, 'version' => md5($body)];
    }

    /**
     * Muat berkas Turtle ke dataset lewat Graph Store Protocol (/data).
     * $ganti = true mengganti isi graph default, false menambahkan.
     */
    public function loadFile(string $path, bool $ganti = false): void
    {
        $isi = file_get_contents($path);

        if ($isi === false) {
            throw new \RuntimeException('Tidak bisa membaca '.$path);
        }

        $request = Http::timeout((int) config('sparql.dump_timeout', 120))
            ->withBody($isi, 'text/turtle; charset=utf-8');

        $url = $this->datasetUrl().'/data?default';
        $res = $ganti ? $request->put($url) : $request->post($url);

        if (! $res->successful()) {
            throw new \RuntimeException('Fuseki HTTP '.$res->status().': '.mb_substr($res->body(), 0, 300));
        }
    }

    public function prefix(): string
    {
        return 'PREFIX ex: <'.config('sparql.vocabulary', self::VOCABULARY).">\n"
            .'PREFIX rdf: <'.self::RDF.">\n"
            .'PREFIX rdfs: <'.self::RDFS.">\n"
            .'PREFIX owl: <http://www.w3.org/2002/07/owl#>'."\n"
            .'PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>'."\n"
            .'PREFIX geo: <http://www.w3.org/2003/01/geo/wgs84_pos#>'."\n";
    }

    /**
     * Ekspresi REGEX untuk FILTER SPARQL: cocok SEBAGIAN dan tidak peka huruf besar-kecil.
     *
     *   regex('?negara', 'filipi')  =>  REGEX(LCASE(STR(?negara)), "filipi", "i")
     *
     * Jadi cukup mengetik sebagian kata ("filipi" -> Filipina, "indo" -> Indonesia).
     * Secara bawaan kata kunci diperlakukan sebagai teks biasa (karakter regex di-escape),
     * jadi aman dipakai untuk input pengguna. Isi $literal = false kalau memang ingin
     * menulis pola regex sendiri, mis. "^fil".
     */
    public static function regex(string $var, string $kata, bool $literal = true): string
    {
        $kata = mb_strtolower(trim($kata));

        if ($literal) {
            // karakter spesial regex XPath/SPARQL diberi backslash
            $kata = preg_replace('/[.\\\\?*+{}()|^$\[\]\-]/', '\\\\$0', $kata) ?? $kata;
        }

        $var = '?'.ltrim($var, '?');

        return 'REGEX(LCASE(STR('.$var.')), "'.self::esc($kata).'", "i")';
    }

    /**
     * FILTER dari beberapa kata kunci yang digabung dengan OR:
     *   FILTER(REGEX(LCASE(STR(?negara)), "filipi", "i") || REGEX(...))
     * Daftar kosong menghasilkan string kosong (tanpa filter).
     *
     * @param  array<int, string>  $kata
     */
    public static function filterRegex(string $var, array $kata): string
    {
        $kata = array_values(array_filter(array_map('trim', $kata), fn (string $k): bool => $k !== ''));

        if ($kata === []) {
            return '';
        }

        return 'FILTER('.implode(' || ', array_map(fn (string $k): string => self::regex($var, $k), $kata)).')';
    }

    /**
     * Cari bandara dengan kata kunci sebagian. Setiap kata harus muncul di salah satu dari
     * nama, kode IATA/ICAO, wilayah, atau negara ("soekarno hatta", "kualanamu", "filipi").
     *
     * @return list<array<string, string|null>> iata, nama, kota, negara
     */
    public function search(string $kata, int $limit = 10): array
    {
        $kataKunci = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($kata)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kataKunci = array_slice($kataKunci, 0, 5);

        if ($kataKunci === []) {
            return [];
        }

        $syarat = implode(' && ', array_map(fn (string $k): string => self::regex('?teks', $k), $kataKunci));

        $rows = $this->query(
            'SELECT DISTINCT ?iata ?nama ?kota ?negara WHERE {
                ?a a ex:Airport ; rdfs:label ?nama ; ex:hasIATACode ?iata ; ex:locatedInCountry ?c .
                ?c rdfs:label ?negara .
                OPTIONAL { ?a ex:hasICAOCode ?icao }
                OPTIONAL { ?a ex:locatedInRegion ?r . ?r rdfs:label ?kota }
                BIND(CONCAT(STR(?nama), " ", STR(?iata), " ", COALESCE(STR(?icao), ""), " ", COALESCE(STR(?kota), ""), " ", STR(?negara)) AS ?teks)
                FILTER('.$syarat.')
            } ORDER BY ?nama LIMIT '.max(1, $limit)
        );

        // urutan: kode IATA persis, lalu nama yang diawali kata kunci, lalu sisanya
        $kunci = mb_strtolower(trim($kata));
        $skor = fn (array $r): int => strtolower((string) $r['iata']) === $kunci
            ? 0
            : (str_starts_with(mb_strtolower((string) $r['nama']), $kunci) ? 1 : 2);

        $urut = array_keys($rows);
        usort($urut, fn (int $a, int $b): int => [$skor($rows[$a]), $a] <=> [$skor($rows[$b]), $b]);

        return array_map(fn (int $i): array => $rows[$i], $urut);
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
