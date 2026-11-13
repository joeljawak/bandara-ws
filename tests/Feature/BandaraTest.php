<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Services\AirportImageService;
use App\Services\DbpediaService;
use App\Support\AirportArt;
use App\Support\Satellite;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\Support\MembuatDataBandara;
use Tests\TestCase;

class BandaraTest extends TestCase
{
    use MembuatDataBandara;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buatDuniaBandara();
    }

    public function test_halaman_detail_memuat_gambar_rdfa_dan_open_graph(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->fakeLayanan());

        $this->get('/bandara/KNO')
            ->assertOk()
            ->assertSee('Kualanamu International Airport')
            ->assertSee('typeof="Airport"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('id="hero-sat"', false);
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
            'en.wikipedia.org/*' => Http::response(['query' => ['pages' => [[
                'title' => 'Kualanamu International Airport',
                'thumbnail' => ['source' => 'https://upload.wikimedia.org/a/KNO.jpg'],
            ]]]]),
            'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => []]]),
            'query.wikidata.org/*' => Http::response(['results' => ['bindings' => []]]),
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

    public function test_bandara_tanpa_foto_memakai_citra_satelit_di_daftar(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'en.wikipedia.org/*' => Http::response(['query' => ['pages' => []]]),
            'query.wikidata.org/*' => Http::response(['results' => ['bindings' => []]]),
        ]);

        $this->get('/cari?q=aek')
            ->assertOk()
            ->assertSee('World_Imagery', false);
    }

    public function test_foto_wikidata_dicocokkan_lewat_kode_iata(): void
    {
        $json = ['results' => ['bindings' => [
            ['iata' => ['value' => 'KNO'], 'img' => ['value' => 'http://commons.wikimedia.org/wiki/Special:FilePath/Kualanamu%202013.jpg']],
            ['iata' => ['value' => 'CGK'], 'img' => ['value' => 'http://commons.wikimedia.org/wiki/Special:FilePath/CGK_Logo.svg']],
        ]]];

        $hasil = AirportImageService::parseWikidata($json, 320);

        $this->assertStringContainsString('Special:FilePath/Kualanamu%202013.jpg?width=320', $hasil['KNO']['url']);
        $this->assertArrayNotHasKey('CGK', $hasil);
    }

    public function test_rumus_tile_satelit_benar(): void
    {
        [$x, $y] = Satellite::tile(51.4778, -0.0014, 10);

        $this->assertSame(511, (int) floor($x));
        $this->assertSame(340, (int) floor($y));
    }

    public function test_beranda_memuat_bagian_tim(): void
    {
        config(['tim' => [['nama' => 'Budi Santoso', 'nim' => '231402001', 'peran' => 'Backend', 'foto' => 'images/tim/tidak-ada.jpg']]]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Tim pengembang')
            ->assertSee('Budi Santoso')
            ->assertSee('231402001')
            ->assertSee('BS');
    }

    public function test_halaman_detail_menampilkan_deskripsi_dari_dbpedia(): void
    {
        Airport::query()->where('iata_code', 'KNO')->update([
            'dbpedia_url' => 'http://dbpedia.org/resource/Kualanamu_International_Airport',
            'opened_year' => 2013,
        ]);

        Http::preventStrayRequests();
        Http::fake($this->fakeLayanan([
            'dbpedia.org/sparql*' => Http::response(['results' => ['bindings' => [[
                'abstrak' => ['value' => 'Bandar Udara Internasional Kualanamu adalah bandara di Deli Serdang.', 'xml:lang' => 'id'],
            ]]]]),
        ]));

        $this->get('/bandara/KNO')
            ->assertOk()
            ->assertSee('Tentang bandara')
            ->assertSee('Bandar Udara Internasional Kualanamu adalah bandara di Deli Serdang.')
            ->assertSee('Tahun dibuka')
            ->assertSee('2013');
    }

    public function test_alamat_dbpedia_divalidasi_dan_kode_persen_diubah(): void
    {
        $uri = DbpediaService::normalisasi('http://dbpedia.org/resource/Soekarno%E2%80%93Hatta_International_Airport');

        $this->assertSame('http://dbpedia.org/resource/Soekarno–Hatta_International_Airport', $uri);
        $this->assertTrue(DbpediaService::valid($uri));
        $this->assertFalse(DbpediaService::valid('http://contoh.com/resource/X'));
        $this->assertFalse(DbpediaService::valid('http://dbpedia.org/resource/X> } DROP'));
    }

    public function test_deskripsi_dbpedia_mengutamakan_bahasa_indonesia(): void
    {
        $json = ['results' => ['bindings' => [
            ['abstrak' => ['value' => 'English text.', 'xml:lang' => 'en']],
            ['abstrak' => ['value' => 'Teks Indonesia.', 'xml:lang' => 'id']],
        ]]];

        $hasil = DbpediaService::parse($json, 'http://dbpedia.org/resource/KNO');

        $this->assertSame('Teks Indonesia.', $hasil['teks']);
        $this->assertSame('id', $hasil['bahasa']);
    }

    public function test_input_pencarian_mengabaikan_kata_umum_seperti_kota(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->fakeLayanan());

        $this->get('/cari?q=kota+Medan')
            ->assertOk()
            ->assertSee('Kualanamu International Airport');
    }

    public function test_halaman_jelajah_mencari_bandara_lewat_nama_kota(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->fakeLayanan());

        $this->get('/bandara?q=Medan')
            ->assertOk()
            ->assertSee('Kualanamu International Airport');
    }

    public function test_halaman_jelajah_memakai_paginasi_nomor_halaman(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->fakeLayanan());

        $this->get('/bandara?page=2')
            ->assertOk()
            ->assertSee('aria-current="page">2', false)
            ->assertSee('page=1', false);
    }

    /**
     * @param  array<string, Response>  $tambahan
     * @return array<string, Response>
     */
    private function fakeLayanan(array $tambahan = []): array
    {
        return $tambahan + [
            'en.wikipedia.org/*' => Http::response(['query' => ['pages' => []]]),
            'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => []]]),
            'query.wikidata.org/*' => Http::response(['results' => ['bindings' => []]]),
            'dbpedia.org/sparql*' => Http::response(['results' => ['bindings' => []]]),
        ];
    }
}
