<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FlightService
{
    // jadwal keberangkatan dan kedatangan dari AviationStack, di-cache 5 menit
    public function departures(string $iata): array
    {
        return $this->ambil('dep', $iata);
    }

    public function arrivals(string $iata): array
    {
        return $this->ambil('arr', $iata);
    }

    /**
     * @param  'dep'|'arr'  $arah
     */
    private function ambil(string $arah, string $iata): array
    {
        $key = config('sparql.aviationstack_key');
        if (! $key) {
            return [];
        }

        return Cache::remember($arah.'_'.$iata, 300, function () use ($key, $arah, $iata) {
            try {
                $res = Http::timeout(10)->get('http://api.aviationstack.com/v1/flights', [
                    'access_key' => $key,
                    $arah.'_iata' => $iata,
                    'limit' => 15,
                ]);

                return $res->successful() ? ($res->json('data') ?? []) : [];
            } catch (\Throwable $e) {
                return [];
            }
        });
    }
}
