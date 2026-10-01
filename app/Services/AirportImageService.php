<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AirportImageService
{
    // ambil foto bandara dari Wikipedia berdasarkan nama, disimpan di cache 7 hari
    public function thumbnail(string $namaBandara): ?string
    {
        $key = 'foto_bandara_' . md5($namaBandara);

        return Cache::remember($key, now()->addDays(7), function () use ($namaBandara) {
            try {
                $res = Http::timeout(5)
                    ->withHeaders(['User-Agent' => 'AeroSearch-TugasKuliah/1.0'])
                    ->get('https://en.wikipedia.org/w/api.php', [
                        'action' => 'query',
                        'format' => 'json',
                        'prop' => 'pageimages',
                        'piprop' => 'thumbnail',
                        'pithumbsize' => 900,
                        'titles' => $namaBandara,
                        'redirects' => 1,
                    ]);

                if (!$res->successful()) {
                    return null;
                }

                foreach ($res->json('query.pages') ?? [] as $page) {
                    if (isset($page['thumbnail']['source'])) {
                        return $page['thumbnail']['source'];
                    }
                }
            } catch (\Throwable $e) {
                // foto tidak wajib ada, biarkan null supaya halaman pakai gambar cadangan
            }

            return null;
        });
    }
}
