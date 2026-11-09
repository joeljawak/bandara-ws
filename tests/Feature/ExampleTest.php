<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExampleTest extends TestCase
{
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
        $endpoint = config('sparql.endpoint');
        Http::preventStrayRequests();
        Http::fake([
            $endpoint => Http::response([
                'results' => [
                    'bindings' => [[
                        'nama' => ['value' => 'Soekarno-Hatta International Airport'],
                        'iata' => ['value' => 'CGK'],
                    ]],
                ],
            ]),
        ]);

        $this->get('/cari?q=Soekarno-Hatta')
            ->assertOk()
            ->assertSee('Soekarno-Hatta International Airport')
            ->assertSee('CGK')
            ->assertSee('Lokasi tidak tersedia');

        Http::assertSent(fn (Request $request): bool => str_contains($request['query'], 'soekarnohatta') &&
            str_contains($request['query'], 'REPLACE(LCASE(STR(?nama)), "[ -]", "")')
        );
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
