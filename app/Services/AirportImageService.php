<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Mengambil foto asli bandara dari Wikipedia (dan Wikimedia Commons sebagai cadangan).
 * Hasilnya disimpan di cache supaya halaman tidak lambat.
 */
class AirportImageService
{
    private const WIKIPEDIA = 'https://en.wikipedia.org/w/api.php';
    private const COMMONS = 'https://commons.wikimedia.org/w/api.php';

    // kata umum yang tidak dipakai untuk mencocokkan nama bandara
    private const UMUM = [
        'airport', 'international', 'domestic', 'regional', 'airfield', 'aerodrome', 'airbase',
        'base', 'field', 'intl', 'bandar', 'bandara', 'udara', 'lanud', 'the', 'and',
    ];

    // foto untuk halaman detail: coba judul persis, lalu pencarian Wikipedia, lalu Commons
    public function photo(string $nama): ?array
    {
        $key = 'foto_detail_' . md5($nama);
        $cache = Cache::get($key);
        if ($cache !== null) {
            return $cache ?: null;
        }

        $hasil = $this->byTitles([$nama], 1200)[$nama] ?? null;
        $hasil ??= $this->bySearch($nama);
        $hasil ??= $this->byCommons($nama);

        Cache::put($key, $hasil ?: false, $hasil ? now()->addDays(7) : now()->addDay());

        return $hasil;
    }

    // foto kecil untuk banyak bandara sekaligus (satu permintaan per 50 nama)
    public function photos(array $names): array
    {
        $names = array_values(array_unique(array_filter($names)));
        $out = [];
        $belum = [];

        foreach ($names as $nama) {
            $cache = Cache::get('foto_kecil_' . md5($nama));
            if ($cache === null) {
                $belum[] = $nama;
            } else {
                $out[$nama] = $cache ?: null;
            }
        }

        foreach (array_chunk($belum, 50) as $kelompok) {
            $dapat = $this->byTitles($kelompok, 320);
            foreach ($kelompok as $nama) {
                $hasil = $dapat[$nama] ?? null;
                Cache::put('foto_kecil_' . md5($nama), $hasil ?: false, $hasil ? now()->addDays(7) : now()->addDay());
                $out[$nama] = $hasil;
            }
        }

        return $out;
    }

    // dipertahankan supaya kode lama tetap jalan
    public function thumbnail(string $namaBandara): ?string
    {
        return $this->photo($namaBandara)['url'] ?? null;
    }

    private function get(string $url, array $params): array
    {
        try {
            $res = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'AeroSearch-TugasKuliah/1.0'])
                ->get($url, $params + ['action' => 'query', 'format' => 'json', 'formatversion' => 2]);

            return $res->successful() ? ($res->json() ?? []) : [];
        } catch (\Throwable $e) {
            // foto tidak wajib ada, halaman memakai gambar cadangan
            return [];
        }
    }

    // judul halaman Wikipedia sama persis dengan nama bandara
    private function byTitles(array $names, int $size): array
    {
        $json = $this->get(self::WIKIPEDIA, [
            'prop' => 'pageimages',
            'piprop' => 'thumbnail',
            'pithumbsize' => $size,
            'redirects' => 1,
            'titles' => implode('|', $names),
        ]);

        return self::parseTitles($json, $names);
    }

    private function bySearch(string $nama): ?array
    {
        $json = $this->get(self::WIKIPEDIA, [
            'generator' => 'search',
            'gsrsearch' => $nama,
            'gsrnamespace' => 0,
            'gsrlimit' => 5,
            'prop' => 'pageimages',
            'piprop' => 'thumbnail',
            'pithumbsize' => 1200,
        ]);

        return self::parseSearch($json, $nama);
    }

    private function byCommons(string $nama): ?array
    {
        $json = $this->get(self::COMMONS, [
            'generator' => 'search',
            'gsrsearch' => $nama,
            'gsrnamespace' => 6,
            'gsrlimit' => 8,
            'prop' => 'imageinfo',
            'iiprop' => 'url',
            'iiurlwidth' => 1200,
        ]);

        return self::parseCommons($json, $nama);
    }

    // ---- bagian di bawah ini tidak memakai jaringan, jadi mudah diuji ----

    // petakan nama yang diminta ke halaman hasil (menangani perubahan judul dan redirect)
    public static function parseTitles(array $json, array $names): array
    {
        $query = $json['query'] ?? [];
        $normal = [];
        foreach ($query['normalized'] ?? [] as $n) {
            $normal[$n['from']] = $n['to'];
        }
        $redirect = [];
        foreach ($query['redirects'] ?? [] as $r) {
            $redirect[$r['from']] = $r['to'];
        }
        $pages = [];
        foreach ($query['pages'] ?? [] as $p) {
            $pages[$p['title']] = $p;
        }

        $out = [];
        foreach ($names as $nama) {
            $t = $nama;
            $t = $normal[$t] ?? $t;
            $t = $redirect[$t] ?? $t;
            $p = $pages[$t] ?? null;
            $url = $p['thumbnail']['source'] ?? null;
            $out[$nama] = ($url && self::layak($url))
                ? ['url' => $url, 'page' => self::wikiUrl($p['title'])]
                : null;
        }

        return $out;
    }

    public static function parseSearch(array $json, string $nama): ?array
    {
        $pages = $json['query']['pages'] ?? [];
        usort($pages, fn ($a, $b) => ($a['index'] ?? 99) <=> ($b['index'] ?? 99));

        foreach ($pages as $p) {
            $url = $p['thumbnail']['source'] ?? null;
            if ($url && self::layak($url) && self::cocok($p['title'] ?? '', $nama)) {
                return ['url' => $url, 'page' => self::wikiUrl($p['title'])];
            }
        }

        return null;
    }

    public static function parseCommons(array $json, string $nama): ?array
    {
        $pages = $json['query']['pages'] ?? [];
        usort($pages, fn ($a, $b) => ($a['index'] ?? 99) <=> ($b['index'] ?? 99));

        foreach ($pages as $p) {
            $info = $p['imageinfo'][0] ?? [];
            $url = $info['thumburl'] ?? ($info['url'] ?? null);
            $judul = $p['title'] ?? '';
            $jpg = (bool) preg_match('/\.jpe?g$/i', $judul);
            if ($url && $jpg && self::layak($url) && self::cocok($judul, $nama)) {
                return ['url' => $url, 'page' => $info['descriptionurl'] ?? $url];
            }
        }

        return null;
    }

    // buang logo, peta, dan gambar vektor
    public static function layak(string $url): bool
    {
        return ! preg_match('/\.svg|logo|map|diagram|seal|flag|coat_of_arms|emblem/i', $url);
    }

    // judul harus memuat kata khas dari nama bandara supaya tidak salah bandara
    public static function cocok(string $judul, string $nama): bool
    {
        $khas = self::kataKhas($nama);
        if ($khas === []) {
            return false;
        }
        $judul = mb_strtolower($judul);
        foreach ($khas as $kata) {
            if (str_contains($judul, $kata)) {
                return true;
            }
        }

        return false;
    }

    public static function kataKhas(string $nama): array
    {
        $kata = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($kata, fn ($k) => mb_strlen($k) >= 4 && ! in_array($k, self::UMUM, true)));
    }

    private static function wikiUrl(string $title): string
    {
        return 'https://en.wikipedia.org/wiki/' . str_replace('%2F', '/', rawurlencode(str_replace(' ', '_', $title)));
    }
}
