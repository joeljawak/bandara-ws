<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Mode live: data dibaca dari Apache Jena Fuseki pada setiap permintaan.
 */
class FusekiLiveTest extends TestCase
{
    use RefreshDatabase;

    private string $berkasSalinan;

    private int $jumlahTriple = 120;

    private bool $fusekiMati = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sparql.live' => true,
            'sparql.cache_seconds' => 0,
            'sparql.endpoint' => 'http://fuseki.test/bandara/sparql',
        ]);

        $this->berkasSalinan = storage_path('framework/cache/fuseki-triples.bin');
        File::delete($this->berkasSalinan);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        File::delete($this->berkasSalinan);

        parent::tearDown();
    }

    private function hitung(int $n): array
    {
        return ['head' => ['vars' => ['n']], 'results' => ['bindings' => [
            ['n' => ['type' => 'literal', 'value' => (string) $n]],
        ]]];
    }

    /**
     * Fuseki palsu yang menjawab COUNT dan CONSTRUCT dari berkas fixture.
     */
    private function fusekiHidup(): void
    {
        $ttl = (string) file_get_contents(base_path('tests/Fixtures/sample-bandara.ttl'));

        Http::fake(function ($request) use ($ttl) {
            $query = (string) ($request->data()['query'] ?? '');

            if ($this->fusekiMati) {
                return Http::response('down', 500);
            }

            return str_contains($query, 'CONSTRUCT')
                ? Http::response($ttl, 200, ['Content-Type' => 'application/n-triples'])
                : Http::response($this->hitung($this->jumlahTriple), 200);
        });
    }

    /**
     * Tiap permintaan sungguhan berjalan di proses PHP baru; di tes, salinan
     * data per-permintaan dilepas supaya meniru itu.
     */
    private function permintaanBaru(): void
    {
        $this->app->forgetScopedInstances();
    }

    private function jumlahQuery(string $kata): int
    {
        return Http::recorded(
            fn ($request): bool => str_contains((string) ($request->data()['query'] ?? ''), $kata)
        )->count();
    }

    public function test_halaman_mengambil_data_dari_fuseki(): void
    {
        $this->fusekiHidup();

        $this->get('/cari?q=Kualanamu')
            ->assertOk()
            ->assertSee('Kualanamu International Airport');

        $this->permintaanBaru();
        $this->get('/bandara/KNO')->assertOk()->assertSee('Kualanamu');

        $this->permintaanBaru();
        $this->getJson('/api/v1/stats')->assertOk();

        $this->assertSame(3, $this->jumlahQuery('CONSTRUCT'), 'tanpa cache, tiap permintaan membaca ulang dari Fuseki');
    }

    public function test_web_tidak_bisa_mencari_kalau_fuseki_mati(): void
    {
        Http::fake(fn () => Http::response('Service Unavailable', 503));

        $this->get('/cari?q=Kualanamu')
            ->assertStatus(503)
            ->assertSee('Apache Jena Fuseki tidak tersedia')
            ->assertDontSee('Kualanamu International Airport');

        $this->get('/')->assertStatus(503);
        $this->get('/bandara/KNO')->assertStatus(503);
        $this->getJson('/api/v1/stats')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Apache Jena Fuseki tidak tersedia, data tidak bisa diambil.');
    }

    public function test_koneksi_ditolak_juga_menghentikan_pencarian(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 7: Failed to connect');
        });

        $this->get('/cari?q=Kualanamu')
            ->assertStatus(503)
            ->assertSee('Fuseki tidak bisa dihubungi');
    }

    public function test_dataset_kosong_menghentikan_pencarian(): void
    {
        Http::fake(fn () => Http::response($this->hitung(0), 200));

        $this->get('/cari?q=Kualanamu')
            ->assertStatus(503)
            ->assertSee('Dataset Fuseki kosong');
    }

    public function test_salinan_singkat_dipakai_ulang_tapi_fuseki_tetap_dicek_tiap_permintaan(): void
    {
        config(['sparql.cache_seconds' => 60]);
        $this->fusekiHidup();

        $this->get('/cari?q=Kualanamu')->assertOk()->assertSee('Kualanamu International Airport');

        $this->permintaanBaru();
        $this->get('/cari?q=Soekarno')->assertOk()->assertSee('Soekarno-Hatta');

        $this->assertSame(1, $this->jumlahQuery('CONSTRUCT'), 'data diunduh sekali');
        $this->assertSame(2, $this->jumlahQuery('COUNT'), 'Fuseki dicek di setiap permintaan');

        // Fuseki mati setelah salinan terisi: web tetap berhenti.
        $this->permintaanBaru();
        $this->fusekiMati = true;
        $this->get('/cari?q=Kualanamu')->assertStatus(503)->assertSee('Apache Jena Fuseki tidak tersedia');
    }

    public function test_perubahan_jumlah_triple_membuat_data_dibaca_ulang(): void
    {
        config(['sparql.cache_seconds' => 60]);

        $this->fusekiHidup();
        $this->get('/cari?q=Kualanamu')->assertOk();
        $this->assertSame(1, $this->jumlahQuery('CONSTRUCT'));

        $this->permintaanBaru();
        $this->get('/cari?q=Kualanamu')->assertOk();
        $this->assertSame(1, $this->jumlahQuery('CONSTRUCT'), 'jumlah sama, salinan dipakai ulang');

        $this->permintaanBaru();
        $this->jumlahTriple = 121;
        $this->get('/cari?q=Kualanamu')->assertOk();
        $this->assertSame(2, $this->jumlahQuery('CONSTRUCT'), 'jumlah triple berubah, data diunduh lagi');
    }

    public function test_robots_tetap_bisa_dibuka_tanpa_fuseki(): void
    {
        Http::fake(fn () => Http::response('down', 500));

        $this->get('/robots.txt')->assertOk();
    }
}
