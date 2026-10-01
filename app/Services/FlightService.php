<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FlightService
{
    // jadwal keberangkatan dari AviationStack, di-cache 5 menit
    public function departures(string $iata): array
    {
        $key = config('sparql.aviationstack_key');
        if (!$key) {
            return [];
        }

        return Cache::remember('dep_' . $iata, 300, function () use ($key, $iata) {
            try {
                $res = Http::timeout(10)->get('http://api.aviationstack.com/v1/flights', [
                    'access_key' => $key,
                    'dep_iata' => $iata,
                    'limit' => 15,
                ]);
                return $res->successful() ? ($res->json('data') ?? []) : [];
            } catch (\Throwable $e) {
                return [];
            }
        });
    }
}
