<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Region;
use App\Services\AirportSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchWilayahTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): void
    {
        $id = Country::create(['external_id' => 'country_Indonesia', 'label' => 'Indonesia', 'member_of_asean' => true]);
        $negara = Region::create(['external_id' => 'region_Q252', 'label' => 'Indonesia', 'country_id' => $id->id]);
        $sumut = Region::create(['external_id' => 'region_Q2140', 'label' => 'Sumatera Utara', 'country_id' => $id->id, 'parent_id' => $negara->id]);
        $deli = Region::create(['external_id' => 'region_Q1', 'label' => 'Deli Serdang', 'country_id' => $id->id, 'parent_id' => $sumut->id]);
        $taput = Region::create(['external_id' => 'region_Q2', 'label' => 'Tapanuli Utara', 'country_id' => $id->id, 'parent_id' => $sumut->id]);
        $sibor = Region::create(['external_id' => 'region_Q3', 'label' => 'Siborongborong', 'country_id' => $id->id, 'parent_id' => $taput->id]);
        $bali = Region::create(['external_id' => 'region_Q4', 'label' => 'Bali', 'country_id' => $id->id, 'parent_id' => $negara->id]);

        $buat = fn (string $iata, string $nama, Region $r, int $pax = 0) => Airport::create([
            'external_id' => 'airport_'.$iata, 'label' => $nama, 'iata_code' => $iata,
            'country_id' => $id->id, 'region_id' => $r->id, 'annual_passengers' => $pax,
        ]);

        $buat('KNO', 'Kualanamu International Airport', $deli, 8000000);
        $buat('DTB', 'Raja Sisingamangaraja XII International Airport', $sibor);
        $buat('DPS', 'Ngurah Rai International Airport', $bali, 20000000);
    }

    public function test_cari_provinsi_menampilkan_bandara_di_wilayah_itu(): void
    {
        $this->seedData();

        $hasil = collect(app(AirportSearch::class)->search('sumatera utara'));

        $this->assertEqualsCanonicalizing(['KNO', 'DTB'], $hasil->pluck('iata')->all());
        // bandara yang paling ramai di atas
        $this->assertSame('KNO', $hasil->first()['iata']);
    }

    public function test_alias_inggris_dan_huruf_besar_tetap_ketemu(): void
    {
        $this->seedData();

        $this->assertCount(2, app(AirportSearch::class)->search('North Sumatra'));
        $this->assertCount(2, app(AirportSearch::class)->search('  SUMATERA   utara '));
    }

    public function test_kode_iata_persis_paling_atas(): void
    {
        $this->seedData();

        $this->assertSame('KNO', app(AirportSearch::class)->search('kno')[0]['iata']);
    }

    public function test_halaman_cari_menampilkan_provinsi(): void
    {
        $this->seedData();

        $this->get('/cari?q=sumatera+utara')
            ->assertOk()
            ->assertSee('Kualanamu International Airport')
            ->assertSee('Siborongborong, Tapanuli Utara, Sumatera Utara')
            ->assertDontSee('Ngurah Rai');
    }

    public function test_input_berbahaya_tidak_merusak_pencarian(): void
    {
        $this->seedData();

        $this->assertSame([], app(AirportSearch::class)->search("'; DROP TABLE airports; --"));
        $this->assertSame([], app(AirportSearch::class)->search('%'));
        $this->assertSame(3, Airport::count());
    }
}
