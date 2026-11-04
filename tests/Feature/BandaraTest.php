<?php

namespace Tests\Feature;

use App\Services\AirportImageService;
use App\Support\AirportArt;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BandaraTest extends TestCase
{
    public function test_halaman_detail_memuat_gambar_rdfa_dan_open_graph(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            config('services.fuseki.url') => Http::sequence()
                ->push(['results' => ['bindings' => [[
                    'nama' => ['value' => 'Kualanamu International Airport'],
                    'negara' => ['value' => 'Indonesia'],
                    'lat' => ['value' => '3.6422'],
                    'long' => ['value' => '98.8853'],
                    'runway' => ['value' => '3750'],
                ]]]])
                ->push(['results' => ['bindings' => []]]),
            'en.wikipedia.org/*' => Http::response(['query' => ['pages' => []]]),
            'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => []]]),
        ]);

        $this->get('/bandara/KNO')
            ->assertOk()
            ->assertSee('Kualanamu International Airport')
            ->assertSee('typeof="Airport"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('<svg', false);
    }

    public function test_gambar_bandara_stabil_dan_berbeda_tiap_bandara(): void
    {
        $data = ['iata' => 'KNO', 'nama' => 'Kualanamu', 'lat' => '3.64', 'lon' => '98.88', 'runway' => 3750, 'penumpang' => 8000000];
        $waktu = new DateTimeImmutable('2026-10-23 05:00:00', new DateTimeZone('UTC'));

        $satu = AirportArt::svg($data, 'hero', $waktu);

        $this->assertSame($satu, AirportArt::svg($data, 'hero', $waktu));
        $this->assertNotSame($satu, AirportArt::svg(['iata' => 'SIN'] + $data, 'hero', $waktu));
        $this->assertNotFalse(simplexml_load_string($satu));
    }

    public function test_gambar_bandara_tetap_jadi_tanpa_koordinat(): void
    {
        $svg = AirportArt::svg(['iata' => 'ABC', 'nama' => 'Tanpa Data'], 'thumb');

        $this->assertNotFalse(simplexml_load_string($svg));
    }

    public function test_hasil_pencarian_memakai_foto_asli_kalau_ada(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            config('sparql.endpoint') => Http::response(['results' => ['bindings' => [[
                'nama' => ['value' => 'Kualanamu International Airport'],
                'iata' => ['value' => 'KNO'],
            ]]]]),
            'en.wikipedia.org/*' => Http::response(['query' => ['pages' => [[
                'title' => 'Kualanamu International Airport',
                'thumbnail' => ['source' => 'https://upload.wikimedia.org/a/KNO.jpg'],
            ]]]]),
        ]);

        $this->get('/cari?q=kualanamu')
            ->assertOk()
            ->assertSee('https://upload.wikimedia.org/a/KNO.jpg', false);
    }

    public function test_logo_dan_peta_tidak_dipakai_sebagai_foto(): void
    {
        $json = ['query' => ['pages' => [
            ['title' => 'Aek Godang Airport', 'thumbnail' => ['source' => 'https://upload.wikimedia.org/b/Logo.svg.png']],
        ]]];

        $hasil = AirportImageService::parseTitles($json, ['Aek Godang Airport']);

        $this->assertNull($hasil['Aek Godang Airport']);
    }

    public function test_pencarian_cadangan_tidak_salah_pilih_bandara(): void
    {
        $json = ['query' => ['pages' => [
            ['title' => 'Jakarta', 'index' => 1, 'thumbnail' => ['source' => 'https://x/Jakarta.jpg']],
            ['title' => 'Aek Godang Airport', 'index' => 2, 'thumbnail' => ['source' => 'https://x/AEG.jpg']],
        ]]];

        $hasil = AirportImageService::parseSearch($json, 'Aek Godang Airport');

        $this->assertSame('https://x/AEG.jpg', $hasil['url']);
    }
}
