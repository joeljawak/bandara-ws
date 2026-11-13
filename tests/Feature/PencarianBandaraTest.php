<?php

namespace Tests\Feature;

use App\Services\AirportSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MembuatDataBandara;
use Tests\TestCase;

class PencarianBandaraTest extends TestCase
{
    use MembuatDataBandara;
    use RefreshDatabase;

    private AirportSearch $search;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buatDuniaBandara();
        $this->search = app(AirportSearch::class);
    }

    public function test_peringkat_kode_iata_nama_dan_kota(): void
    {
        $this->assertSame('CGK', $this->hasilPertama('cgk'));
        $this->assertSame('CGK', $this->hasilPertama('WIII'));
        $this->assertSame('CGK', $this->hasilPertama('soekarno'));
        $this->assertSame('SIN', $this->hasilPertama('changi'));
        $this->assertSame('KUL', $this->hasilPertama('kuala lumpur'));
    }

    public function test_toleransi_salah_ketik_satu_dua_huruf(): void
    {
        $this->assertSame('BKK', $this->hasilPertama('Suvarnabumi'));
        $this->assertSame('KNO', $this->hasilPertama('Kualanamo'));
        $this->assertSame('SIN', $this->hasilPertama('Changy'));
    }

    public function test_alias_populer_dan_nama_lokal_bisa_dicari(): void
    {
        $this->assertSame('CGK', $this->hasilPertama('Cengkareng'));
        $this->assertSame('KUL', $this->hasilPertama('KLIA'));
        $this->assertSame('DPS', $this->hasilPertama('Ngurah Rai'));
        $this->assertSame('KNO', $this->hasilPertama('Medan'));
    }

    public function test_paginasi_membatasi_jumlah_dan_menyimpan_query(): void
    {
        $halaman = $this->search->cari(['per_page' => 5, 'page' => 2]);

        $this->assertSame(29, $halaman->total());
        $this->assertCount(5, $halaman->items());
        $this->assertSame(2, $halaman->currentPage());
        $this->assertTrue($halaman->hasMorePages());

        $this->get('/cari?page=2')
            ->assertOk()
            ->assertSee('aria-current="page">2', false)
            ->assertSee('page=1', false)
            ->assertSee('Berikutnya', false);
    }

    public function test_filter_negara_kategori_asean_dan_rute(): void
    {
        $this->assertSame(1, $this->search->cari(['negara' => 'Malaysia'])->total());
        $this->assertSame(1, $this->search->cari(['asean' => '0'])->total());
        $this->assertSame(21, $this->search->cari(['kategori' => 'Domestic'])->total());
        $this->assertSame(2, $this->search->cari(['rute' => '1'])->total());
    }

    public function test_hasil_bandara_maskapai_dan_rute_digabung(): void
    {
        $this->get('/cari?q=garuda')
            ->assertOk()
            ->assertSee('Garuda Indonesia')
            ->assertSee('Maskapai');

        $this->get('/cari?q=CGK+DPS')
            ->assertOk()
            ->assertSee('Rute')
            ->assertSee('CGK')
            ->assertSee('DPS');
    }

    public function test_autocomplete_mengembalikan_maksimal_delapan_saran(): void
    {
        $response = $this->getJson('/cari/saran?q=ba');

        $response->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonStructure(['data' => [['judul', 'sub', 'url', 'iata']]]);

        $this->getJson('/cari/saran?q=c')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_pencarian_radius_mengutamakan_bandara_terdekat(): void
    {
        $hasil = $this->search->cari(['near' => '-6.1256,106.6558', 'radius' => 50])->items();

        $this->assertSame('CGK', $hasil[0]['iata']);
        $this->assertLessThan(50, $hasil[0]['jarak_km']);

        $this->get('/cari?near=-6.1256,106.6558&radius=50')
            ->assertOk()
            ->assertSee('Bandara dalam radius 50 km');
    }

    public function test_input_berbahaya_tidak_merusak_pencarian(): void
    {
        $this->get('/cari?q='.urlencode("CGK' OR 1=1 --"))
            ->assertOk();

        $this->get('/cari?q='.urlencode('<script>alert(1)</script>'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    private function hasilPertama(string $kataKunci): ?string
    {
        return $this->search->cari(['q' => $kataKunci, 'per_page' => 1])->items()[0]['iata'] ?? null;
    }
}
