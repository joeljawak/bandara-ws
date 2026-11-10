<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Country;
use App\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RelationalChatTest extends TestCase
{
    use RefreshDatabase;

    private Airport $cgk;

    private Airport $dps;

    protected function setUp(): void
    {
        parent::setUp();

        $indonesia = Country::create([
            'external_id' => 'country-id',
            'label' => 'Indonesia',
            'member_of_asean' => true,
        ]);
        $malaysia = Country::create([
            'external_id' => 'country-my',
            'label' => 'Malaysia',
            'member_of_asean' => true,
        ]);

        $this->cgk = Airport::create([
            'external_id' => 'airport-cgk',
            'label' => 'Soekarno Hatta International Airport',
            'iata_code' => 'CGK',
            'country_id' => $indonesia->id,
            'annual_passengers' => 50000000,
            'longest_runway_length' => 3600,
        ]);
        $this->dps = Airport::create([
            'external_id' => 'airport-dps',
            'label' => 'I Gusti Ngurah Rai International Airport',
            'iata_code' => 'DPS',
            'country_id' => $indonesia->id,
            'annual_passengers' => 20000000,
            'longest_runway_length' => 3000,
        ]);
        Airport::create([
            'external_id' => 'airport-kul',
            'label' => 'Kuala Lumpur International Airport',
            'iata_code' => 'KUL',
            'country_id' => $malaysia->id,
        ]);

        $garuda = Airline::create(['external_id' => 'airline-ga', 'label' => 'Garuda Indonesia', 'airline_code' => 'GA']);
        $airasia = Airline::create(['external_id' => 'airline-ak', 'label' => 'AirAsia', 'airline_code' => 'AK']);

        Route::create([
            'external_id' => 'route-1',
            'origin_airport_id' => $this->cgk->id,
            'destination_airport_id' => $this->dps->id,
            'airline_id' => $garuda->id,
        ]);
        Route::create([
            'external_id' => 'route-2',
            'origin_airport_id' => $this->cgk->id,
            'destination_airport_id' => $this->dps->id,
            'airline_id' => $airasia->id,
        ]);
    }

    public function test_chat_menjawab_jumlah_bandara_dari_database_relasional(): void
    {
        $this->getJson('/chat?q='.urlencode('Berapa jumlah bandara di Indonesia?'))
            ->assertOk()
            ->assertJsonPath('text', 'Ada 2 bandara di Indonesia menurut data yang tersimpan.');
    }

    public function test_chat_menampilkan_info_bandara_dari_kode_iata(): void
    {
        $this->getJson('/chat?q='.urlencode('Info CGK'))
            ->assertOk()
            ->assertJsonPath('items.0.judul', 'Buka halaman Soekarno Hatta International Airport')
            ->assertJsonPath('items.0.url', route('data.airport', 'CGK'));
    }

    public function test_chat_menampilkan_rute_keberangkatan(): void
    {
        $response = $this->getJson('/chat?q='.urlencode('Rute dari CGK'))
            ->assertOk()
            ->assertJsonPath('items.0.judul', 'I Gusti Ngurah Rai International Airport (DPS)')
            ->assertJsonPath('items.0.sub', '2 maskapai');

        $this->assertStringContainsString('Rute dari Soekarno Hatta', $response->json('text'));
    }

    public function test_chat_menampilkan_maskapai_dari_database_relasional(): void
    {
        $text = $this->getJson('/chat?q='.urlencode('maskapai di CGK'))
            ->assertOk()
            ->json('text');

        $this->assertStringContainsString('AirAsia', $text);
        $this->assertStringContainsString('Garuda Indonesia', $text);
    }

    public function test_chat_tetap_berfungsi_tanpa_memanggil_fuseki(): void
    {
        Http::preventStrayRequests();

        $this->getJson('/chat?q='.urlencode('Info Kualanamu'))
            ->assertOk()
            ->assertJsonPath('text', 'Aku belum menemukan bandara untuk "kualanamu". Coba nama lain, kode IATA, atau nama kotanya.');
    }
}
