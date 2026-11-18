<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Region;
use App\Services\AirportSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CariWilayahTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $idn = Country::create(['external_id' => 'country_idn', 'label' => 'Indonesia', 'member_of_asean' => true]);
        $phl = Country::create(['external_id' => 'country_phl', 'label' => 'Filipina', 'member_of_asean' => true]);

        $akarIdn = Region::create(['external_id' => 'r_idn', 'label' => 'Indonesia', 'country_id' => $idn->id]);
        $sumut = Region::create(['external_id' => 'r_sumut', 'label' => 'Sumatera Utara', 'country_id' => $idn->id, 'parent_id' => $akarIdn->id]);
        $deli = Region::create(['external_id' => 'r_deli', 'label' => 'Deli Serdang', 'country_id' => $idn->id, 'parent_id' => $sumut->id]);
        $aceh = Region::create(['external_id' => 'r_aceh', 'label' => 'Aceh', 'country_id' => $idn->id, 'parent_id' => $akarIdn->id]);
        $kaltara = Region::create(['external_id' => 'r_kaltara', 'label' => 'Kalimantan Utara', 'country_id' => $idn->id, 'parent_id' => $akarIdn->id]);
        $bali = Region::create(['external_id' => 'r_bali', 'label' => 'Bali', 'country_id' => $idn->id, 'parent_id' => $akarIdn->id]);

        // Wilayah Filipina yang namanya mengandung kata "Utara"
        $zamboanga = Region::create(['external_id' => 'r_zu', 'label' => 'Zamboanga Utara', 'country_id' => $phl->id]);

        $buat = fn (string $iata, string $nama, Country $negara, Region $wilayah, int $pax = 0) => Airport::create([
            'external_id' => 'airport_'.$iata, 'label' => $nama, 'iata_code' => $iata,
            'country_id' => $negara->id, 'region_id' => $wilayah->id, 'annual_passengers' => $pax,
        ]);

        $buat('KNO', 'Kualanamu International Airport', $idn, $deli, 8000000);
        $buat('BTJ', 'Sultan Iskandar Muda International Airport', $idn, $aceh, 2000000);
        $buat('TRK', 'Juwata International Airport', $idn, $kaltara, 1000000);
        $buat('DPS', 'Ngurah Rai International Airport', $idn, $bali, 20000000);
        $buat('DPL', 'Dipolog Airport', $phl, $zamboanga, 100000);
    }

    /**
     * @return list<string>
     */
    private function kode(string $q): array
    {
        return collect(app(AirportSearch::class)->cari(['q' => $q, 'per_page' => 50])->items())
            ->pluck('iata')
            ->all();
    }

    public function test_sumatera_utara_tidak_membawa_bandara_luar_negeri(): void
    {
        $this->assertSame(['KNO'], $this->kode('sumatera utara'));
    }

    public function test_pencarian_provinsi_tidak_peduli_huruf_besar_bahasa_inggris_dan_kata_umum(): void
    {
        $this->assertSame(['KNO'], $this->kode('  SUMATERA   utara '));
        $this->assertSame(['KNO'], $this->kode('North Sumatra'));
        $this->assertSame(['KNO'], $this->kode('bandara di sumatera utara'));
    }

    public function test_sumatera_menampilkan_semua_provinsi_di_pulau_sumatera_saja(): void
    {
        $hasil = $this->kode('sumatera');

        $this->assertEqualsCanonicalizing(['KNO', 'BTJ'], $hasil);
        $this->assertSame($hasil, $this->kode('Sumatra'));
        $this->assertSame($hasil, $this->kode('pulau sumatera'));
    }

    public function test_kata_utara_saja_tidak_dianggap_sumatera(): void
    {
        $this->assertNotContains('DPL', $this->kode('kalimantan utara'));
        $this->assertSame(['TRK'], $this->kode('kalimantan utara'));
    }

    public function test_salah_ketik_provinsi_tetap_ketemu(): void
    {
        $this->assertSame(['KNO'], $this->kode('sumatera utra'));
    }

    public function test_kode_iata_dan_nama_bandara_tetap_bekerja(): void
    {
        $this->assertSame(['KNO'], $this->kode('kno'));
        $this->assertSame(['KNO'], $this->kode('kualanamu'));
        $this->assertSame(['KNO'], $this->kode('kualanamo'));
    }

    public function test_halaman_cari_sumatera_tidak_menampilkan_bandara_luar_sumatera(): void
    {
        $this->get('/cari?q=sumatera')
            ->assertOk()
            ->assertSee('Kualanamu International Airport')
            ->assertSee('Sultan Iskandar Muda International Airport')
            ->assertDontSee('Dipolog Airport')
            ->assertDontSee('Ngurah Rai');
    }
}
