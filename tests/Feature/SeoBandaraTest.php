<?php

namespace Tests\Feature;

use App\Models\Airport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\MembuatDataBandara;
use Tests\TestCase;

class SeoBandaraTest extends TestCase
{
    use MembuatDataBandara;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buatDuniaBandara();
        Http::preventStrayRequests();
        Http::fake([
            'en.wikipedia.org/*' => Http::response(['query' => ['pages' => []]]),
            'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => []]]),
            'query.wikidata.org/*' => Http::response(['results' => ['bindings' => []]]),
            'dbpedia.org/sparql*' => Http::response(['results' => ['bindings' => []]]),
        ]);
    }

    public function test_detail_memuat_meta_canonical_open_graph_dan_json_ld(): void
    {
        $this->get('/bandara/CGK')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('bandara.show', 'CGK').'">', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"Airport"', false)
            ->assertSee('typeof="Airport"', false)
            ->assertSee('property="iataCode"', false);
    }

    public function test_beranda_memuat_json_ld_website_dan_search_action(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('"@type":"SearchAction"', false)
            ->assertSee(number_format(Airport::query()->count(), 0, ',', '.'), false);
    }

    public function test_sitemap_dan_robots_dihasilkan_dinamis(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('bandara.show', 'CGK'), false)
            ->assertSee(route('home'), false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    public function test_detail_tetap_tampil_saat_fuseki_dan_layanan_luar_mati(): void
    {
        Http::fake(['*' => Http::response('layanan mati', 500)]);

        $this->get('/bandara/CGK')
            ->assertOk()
            ->assertSee('Soekarno-Hatta International Airport')
            ->assertSee('Rute langsung dari CGK');
    }

    public function test_url_data_bandara_dialihkan_permanen_ke_url_kanonik(): void
    {
        $this->get('/data/bandara/CGK')
            ->assertStatus(301)
            ->assertRedirect(route('bandara.show', 'CGK'));
    }

    public function test_halaman_tidak_ditemukan_memberi_petunjuk(): void
    {
        $this->get('/bandara/XYZ')
            ->assertNotFound();
    }
}
