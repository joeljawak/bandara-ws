<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Models\Airport;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function robots(): Response
    {
        $isi = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /cari?*',
            'Disallow: /chat',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ]);

        return response($isi, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHours(6), function (): string {
            $urls = [
                ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
                ['loc' => route('search'), 'priority' => '0.8', 'changefreq' => 'daily'],
                ['loc' => route('bandara.index'), 'priority' => '0.8', 'changefreq' => 'daily'],
                ['loc' => route('data.airports'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['loc' => route('data.airlines'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['loc' => route('data.routes'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['loc' => route('data.map'), 'priority' => '0.5', 'changefreq' => 'weekly'],
                ['loc' => route('route.index'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ];

            Airport::query()
                ->whereNotNull('iata_code')
                ->orderBy('iata_code')
                ->cursor()
                ->each(function (Airport $airport) use (&$urls): void {
                    $urls[] = [
                        'loc' => route('bandara.show', $airport->iata_code),
                        'priority' => '0.7',
                        'changefreq' => 'monthly',
                        'lastmod' => optional($airport->updated_at)->toAtomString(),
                    ];
                });

            Airline::query()
                ->whereNotNull('label')
                ->orderBy('label')
                ->cursor()
                ->each(function (Airline $airline) use (&$urls): void {
                    $urls[] = [
                        'loc' => route('data.airlines', ['q' => $airline->label]),
                        'priority' => '0.4',
                        'changefreq' => 'monthly',
                        'lastmod' => optional($airline->updated_at)->toAtomString(),
                    ];
                });

            $baris = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

            foreach ($urls as $url) {
                $baris[] = '  <url>';
                $baris[] = '    <loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>';
                if (! empty($url['lastmod'])) {
                    $baris[] = '    <lastmod>'.htmlspecialchars($url['lastmod'], ENT_XML1).'</lastmod>';
                }
                $baris[] = '    <changefreq>'.$url['changefreq'].'</changefreq>';
                $baris[] = '    <priority>'.$url['priority'].'</priority>';
                $baris[] = '  </url>';
            }

            $baris[] = '</urlset>';

            return implode("\n", $baris)."\n";
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
