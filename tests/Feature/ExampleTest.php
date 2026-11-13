<?php

namespace Tests\Feature;

use App\Models\Airport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('class="hero"', false)
            ->assertSee('name="q"', false);
    }

    public function test_search_results_without_a_city_do_not_crash_the_page(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'en.wikipedia.org/*' => Http::response(['query' => ['pages' => []]]),
            'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => []]]),
            'query.wikidata.org/*' => Http::response(['results' => ['bindings' => []]]),
        ]);

        Airport::create([
            'external_id' => 'airport_CGK',
            'label' => 'Soekarno-Hatta International Airport',
            'iata_code' => 'CGK',
        ]);

        $this->get('/cari?q=Soekarno-Hatta')
            ->assertOk()
            ->assertSee('Soekarno-Hatta International Airport')
            ->assertSee('CGK')
            ->assertSee('Lokasi tidak tersedia');
    }

    public function test_null_country_filter_does_not_crash_bandara_listing(): void
    {
        $endpoint = config('services.fuseki.url');
        Http::preventStrayRequests();
        Http::fake([
            $endpoint => Http::response(['results' => ['bindings' => []]]),
        ]);

        $response = $this->call(
            'GET',
            '/bandara',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['q' => null, 'negara' => null]),
        );

        $response->assertOk();
    }
}
