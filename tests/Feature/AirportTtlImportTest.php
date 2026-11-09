<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Country;
use App\Models\Region;
use App\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AirportTtlImportTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/Fixtures/sample-bandara.ttl');
    }

    public function test_perintah_impor_mengisi_seluruh_tabel_relasional(): void
    {
        $this->artisan('import:airports-ttl', [
            'file' => $this->fixture,
            '--fresh' => true,
            '--no-supplement' => true,
        ])->assertSuccessful();

        $this->assertSame(1, Country::query()->count());
        $this->assertSame(2, Region::query()->count());
        $this->assertSame(2, Airport::query()->count());
        $this->assertSame(1, Airline::query()->count());
        $this->assertSame(1, Route::query()->count());
        $this->assertSame(1, DB::table('airline_hub')->count());
        $this->assertSame(2, DB::table('airline_airport')->count());

        $country = Country::query()->where('external_id', 'country_ID')->firstOrFail();
        $this->assertSame('Indonesia', $country->label);
        $this->assertTrue($country->member_of_asean);

        $jakarta = Region::query()->where('external_id', 'region_JK')->firstOrFail();
        $sumut = Region::query()->where('external_id', 'region_Sumut')->firstOrFail();
        $this->assertSame($jakarta->id, $sumut->parent_id);
        $this->assertSame($country->id, $sumut->country_id);

        $cgk = Airport::query()->where('iata_code', 'CGK')->firstOrFail();
        $this->assertSame('Soekarno-Hatta International Airport', $cgk->label);
        $this->assertSame('WIII', $cgk->icao_code);
        $this->assertSame($country->id, $cgk->country_id);
        $this->assertSame($jakarta->id, $cgk->region_id);
        $this->assertTrue($cgk->in_asean);
        $this->assertEqualsWithDelta(-6.1256, $cgk->latitude, 0.000001);
        $this->assertSame(54000000, $cgk->annual_passengers);
        $this->assertSame('https://www.soekarnohatta-airport.co.id/', $cgk->website);

        $kno = Airport::query()->where('iata_code', 'KNO')->firstOrFail();
        $this->assertSame($cgk->id, $kno->nearest_airport_id);
        $this->assertSame($sumut->id, $kno->region_id);

        $airline = Airline::query()->where('airline_code', 'GA')->firstOrFail();
        $this->assertSame('Garuda Indonesia', $airline->label);
        $this->assertSame($cgk->id, $airline->hub_airport_id);
        $this->assertTrue($airline->hubs->contains($cgk));
        $this->assertTrue($airline->airports->contains($kno));

        $route = Route::query()->firstOrFail();
        $this->assertSame($cgk->id, $route->origin_airport_id);
        $this->assertSame($kno->id, $route->destination_airport_id);
        $this->assertSame($airline->id, $route->airline_id);
    }

    public function test_impor_bersifat_idempoten(): void
    {
        $arguments = [
            'file' => $this->fixture,
            '--fresh' => true,
            '--no-supplement' => true,
        ];

        $this->artisan('import:airports-ttl', $arguments)->assertSuccessful();
        $this->artisan('import:airports-ttl', $arguments)->assertSuccessful();

        $this->assertSame(2, Airport::query()->count());
        $this->assertSame(1, Airline::query()->count());
        $this->assertSame(1, Route::query()->count());
    }

    public function test_halaman_detail_lama_menampilkan_rute_dari_database_relasional(): void
    {
        $this->artisan('import:airports-ttl', [
            'file' => $this->fixture,
            '--fresh' => true,
            '--no-supplement' => true,
        ])->assertSuccessful();

        Http::preventStrayRequests();
        Http::fake([
            config('services.fuseki.url') => Http::response(['results' => ['bindings' => [[
                'nama' => ['value' => 'Soekarno-Hatta International Airport'],
                'negara' => ['value' => 'Indonesia'],
                'lat' => ['value' => '-6.1256'],
                'long' => ['value' => '106.6558'],
            ]]]]),
            'en.wikipedia.org/*' => Http::response(['query' => ['pages' => []]]),
            'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => []]]),
            'query.wikidata.org/*' => Http::response(['results' => ['bindings' => []]]),
            'dbpedia.org/sparql*' => Http::response(['results' => ['bindings' => []]]),
        ]);

        $this->get('/bandara/CGK')
            ->assertOk()
            ->assertSee('Rute dari CGK')
            ->assertSee('Kualanamu International Airport');
    }
}
