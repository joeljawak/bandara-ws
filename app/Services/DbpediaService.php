<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Mengambil deskripsi bandara dari DBpedia lewat SPARQL endpoint publiknya.
 * Ini bagian "SPARQL endpoint eksternal" yang dipakai langsung oleh aplikasi.
 */
class DbpediaService
{
    private const ENDPOINT = 'https://dbpedia.org/sparql';

    public function describe(?string $uri): ?array
    {
        $uri = $uri ? self::normalisasi($uri) : null;
        if (! $uri || ! self::valid($uri)) {
            return null;
        }

        $key = 'dbpedia_' . md5($uri);
        $cache = Cache::get($key);
        if ($cache !== null) {
            return $cache ?: null;
        }

        $hasil = null;
        try {
            $res = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'BandaraASEAN-TugasKuliah/1.0', 'Accept' => 'application/sparql-results+json'])
                ->get(self::ENDPOINT, ['query' => self::query($uri), 'format' => 'application/sparql-results+json']);
            if ($res->successful()) {
                $hasil = self::parse($res->json() ?? [], $uri);
            }
        } catch (\Throwable $e) {
            // deskripsi tidak wajib ada, halaman tetap tampil tanpa bagian ini
        }

        Cache::put($key, $hasil ?: false, $hasil ? now()->addDays(7) : now()->addHours(6));

        return $hasil;
    }

    // alamat DBpedia disimpan tanpa kode persen, jadi %E2%80%93 harus diubah ke karakter aslinya
    public static function normalisasi(string $uri): string
    {
        return rawurldecode(trim($uri));
    }

    public static function valid(string $uri): bool
    {
        return (bool) preg_match('#^https?://dbpedia\.org/resource/[^\s<>"{}|\\\\^`]+$#u', $uri);
    }

    public static function query(string $uri): string
    {
        return 'PREFIX dbo: <http://dbpedia.org/ontology/> '
            . 'SELECT ?abstrak WHERE { <' . $uri . '> dbo:abstract ?abstrak . FILTER(LANG(?abstrak) IN ("id", "en")) }';
    }

    // utamakan bahasa Indonesia, lalu Inggris, dan potong di akhir kalimat
    public static function parse(array $json, string $uri): ?array
    {
        $id = $en = null;
        foreach ($json['results']['bindings'] ?? [] as $b) {
            $teks = trim($b['abstrak']['value'] ?? '');
            $bahasa = $b['abstrak']['xml:lang'] ?? '';
            if ($bahasa === 'id') {
                $id = $teks;
            } elseif ($bahasa === 'en') {
                $en = $teks;
            }
        }
        $teks = $id ?: $en;
        if (! $teks) {
            return null;
        }

        return [
            'teks' => self::potong($teks, 650),
            'bahasa' => $id ? 'id' : 'en',
            'halaman' => str_replace('/resource/', '/page/', $uri),
        ];
    }

    private static function potong(string $teks, int $maks): string
    {
        if (mb_strlen($teks) <= $maks) {
            return $teks;
        }
        $potongan = mb_substr($teks, 0, $maks);
        $akhir = mb_strrpos($potongan, '. ');

        return $akhir !== false && $akhir > $maks / 2
            ? mb_substr($potongan, 0, $akhir + 1)
            : rtrim($potongan) . '...';
    }
}
