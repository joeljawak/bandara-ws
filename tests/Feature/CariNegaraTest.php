<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Region;
use App\Services\AirportSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Query berupa nama negara ("indo", "indonesia", "philippines") hanya boleh
 * menampilkan bandara negara itu, sama seperti query nama provinsi.
 */
class CariNegaraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $idn = Country::create(['external_id' => 'country_idn', 'label' => 'Indonesia', 'member_of_asean' => true]);
        $phl = Country::create(['external_id' => 'country_phl', 'label' => 'Filipina', 'member_of_asean' => true]);
        $prk = Country::create(['external_id' => 'country_prk', 'label' => 'North Korea', 'member_of_asean' => false]);
        $png = Country::create(['external_id' => 'country_png', 'label' => 'Papua New Guinea', 'member_of_asean' => false]);

        $akar = Region::create(['external_id' => 'r_idn', 'label' => 'Indonesia', 'country_id' => $idn->id]);
        $sumut = Region::create(['external_id' => 'r_sumut', 'label' => 'Sumatera Utara', 'country_id' => $idn->id, 'parent_id' => $akar->id]);
        $deli = Region::create(['external_id' => 'r_deli', 'label' => 'Deli Serdang', 'country_id' => $idn->id, 'parent_id' => $sumut->id]);
        $aceh = Region::create(['external_id' => 'r_aceh', 'label' => 'Aceh', 'country_id' => $idn->id, 'parent_id' => $akar->id]);
        $papua = Region::create(['external_id' => 'r_papua', 'label' => 'Papua', 'country_id' => $idn->id, 'parent_id' => $akar->id]);
        $zamboanga = Region::create(['external_id' => 'r_zu', 'label' => 'Zamboanga Utara', 'country_id' => $phl->id]);
        $pyongyang = Region::create(['external_id' => 'r_pyo', 'label' => 'Pyongyang', 'country_id' => $prk->id]);
        $moresby = Region::create(['external_id' => 'r_pom', 'label' => 'Port Moresby', 'country_id' => $png->id]);

        $buat = fn (string $iata, string $nama, Country $negara, Region $wilayah, int $pax = 0) => Airport::create([
            'external_id' => 'airport_'.$iata, 'label' => $nama, 'iata_code' => $iata,
            'country_id' => $negara->id, 'region_id' => $wilayah->id, 'annual_passengers' => $pax,
        ]);

        $buat('KNO', 'Kualanamu International Airport', $idn, $deli, 8000000);
        $buat('BTJ', 'Sultan Iskandar Muda International Airport', $idn, $aceh, 2000000);
        $buat('DJJ', 'Sentani Airport', $idn, $papua, 1000000);
        $buat('DPL', 'Dipolog Airport', $phl, $zamboanga, 100000);
        $buat('FNJ', 'Pyongyang Sunan International Airport', $prk, $pyongyang, 500000);
        $buat('POM', 'Jacksons International Airport', $png, $moresby, 1500000);
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

    public function test_indo_hanya_menampilkan_bandara_indonesia(): void
    {
        $indonesia = ['KNO', 'BTJ', 'DJJ'];

        $this->assertEqualsCanonicalizing($indonesia, $this->kode('indo'));
        $this->assertEqualsCanonicalizing($indonesia, $this->kode('Indonesia'));
        $this->assertEqualsCanonicalizing($indonesia, $this->kode('  INDONESIA '));
        $this->assertEqualsCanonicalizing($indonesia, $this->kode('bandara indonesia'));
    }

    public function test_hasil_diurutkan_dari_penumpang_terbanyak(): void
    {
        $this->assertSame(['KNO', 'BTJ', 'DJJ'], $this->kode('indonesia'));
    }

    public function test_nama_negara_dalam_bahasa_inggris_dan_alias(): void
    {
        $this->assertSame(['DPL'], $this->kode('philippines'));
        $this->assertSame(['DPL'], $this->kode('filipina'));
        $this->assertSame(['POM'], $this->kode('png'));
    }

    public function test_negara_digabung_dengan_kata_lain_tetap_di_dalam_negara(): void
    {
        $this->assertSame(['KNO'], $this->kode('indonesia kualanamu'));
        $this->assertSame(['KNO'], $this->kode('kualanamu indonesia'));
        $this->assertSame(['KNO'], $this->kode('indonesia sumatera utara'));
        $this->assertSame([], $this->kode('indonesia xyzabc'));
    }

    public function test_provinsi_tidak_dianggap_negara(): void
    {
        // "north sumatra" bukan awalan "North Korea"
        $this->assertSame(['KNO'], $this->kode('north sumatra'));
        // "papua" tetap provinsi Papua, bukan Papua New Guinea
        $this->assertSame(['DJJ'], $this->kode('papua'));
        $this->assertSame(['POM'], $this->kode('papua new guinea'));
    }

    public function test_bandara_negara_lain_tidak_bocor_ke_hasil_indonesia(): void
    {
        $negara = collect(app(AirportSearch::class)->cari(['q' => 'indo', 'per_page' => 50])->items())
            ->pluck('negara')
            ->unique()
            ->values()
            ->all();

        $this->assertSame(['Indonesia'], $negara);
    }
}
