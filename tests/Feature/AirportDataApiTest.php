<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirportDataApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('import:airports-ttl', [
            'file' => base_path('tests/Fixtures/sample-bandara.ttl'),
            '--fresh' => true,
            '--no-supplement' => true,
        ])->assertSuccessful();
    }

    public function test_api_bandara_bisa_dicari_dan_dipaginasi(): void
    {
        $this->getJson('/api/v1/airports')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'label', 'iata_code', 'country', 'departure_routes_count']],
                'links',
                'meta',
            ])
            ->assertJsonFragment(['iata_code' => 'CGK'])
            ->assertJsonFragment(['iata_code' => 'KNO']);

        $this->getJson('/api/v1/airports?q=garuda')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/airports?q=kualanamu')
            ->assertOk()
            ->assertJsonPath('data.0.iata_code', 'KNO');
    }

    public function test_api_detail_bandara_memuat_relasi(): void
    {
        $this->getJson('/api/v1/airports/CGK')
            ->assertOk()
            ->assertJsonPath('data.iata_code', 'CGK')
            ->assertJsonPath('data.label', 'Soekarno-Hatta International Airport')
            ->assertJsonPath('data.country.label', 'Indonesia')
            ->assertJsonPath('data.region.label', 'DKI Jakarta')
            ->assertJsonPath('data.departure_routes_count', 1);
    }

    public function test_api_maskapai_rute_dan_statistik(): void
    {
        $this->getJson('/api/v1/airlines')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Garuda Indonesia')
            ->assertJsonPath('data.0.routes_count', 1);

        $this->getJson('/api/v1/routes')
            ->assertOk()
            ->assertJsonPath('data.0.origin.iata_code', 'CGK')
            ->assertJsonPath('data.0.destination.iata_code', 'KNO')
            ->assertJsonPath('data.0.airline.airline_code', 'GA');

        $this->getJson('/api/v1/stats')
            ->assertOk()
            ->assertJsonPath('data.airports', 2)
            ->assertJsonPath('data.airlines', 1)
            ->assertJsonPath('data.routes', 1)
            ->assertJsonPath('data.top_countries.0.airports', 2);
    }

    public function test_halaman_web_data_relasional_dapat_dibuka(): void
    {
        $this->get('/data/bandara')
            ->assertOk()
            ->assertSee('Data bandara')
            ->assertSee('Soekarno-Hatta International Airport');

        $this->get('/data/bandara/CGK')
            ->assertOk()
            ->assertSee('Soekarno-Hatta International Airport')
            ->assertSee('Rute keberangkatan')
            ->assertSee('Kualanamu International Airport');

        $this->get('/data/maskapai')
            ->assertOk()
            ->assertSee('Garuda Indonesia');

        $this->get('/data/rute')
            ->assertOk()
            ->assertSee('Soekarno-Hatta International Airport')
            ->assertSee('Kualanamu International Airport');

        $this->get('/data/peta')
            ->assertOk()
            ->assertSee('id="peta-data"', false);
    }
}
