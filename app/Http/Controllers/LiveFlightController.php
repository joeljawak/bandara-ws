<?php

namespace App\Http\Controllers;

use App\Services\LiveFlightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveFlightController extends Controller
{
    public function __construct(private LiveFlightService $flightService) {}

    /**
     * Tampilan halaman Radar Live Pesawat.
     */
    public function index(): View
    {
        $airports = $this->flightService->getRadarAirports();

        return view('radar', [
            'airports' => $airports,
            'amapKey' => config('services.amap.key', '103f4d91ec58beeb70030533137c56b5'),
            'seo' => [
                'title' => 'Radar Penerbangan Live - BANDARA ASEAN',
                'description' => 'Pantau posisi pesawat secara langsung (live ADS-B) di kawasan Indonesia dan Asia Tenggara secara real-time.',
            ],
        ]);
    }

    /**
     * Endpoint API JSON untuk data posisi pesawat live.
     * Dipanggil berkala oleh browser (polling setiap 8-10 detik).
     */
    public function api(Request $request): JsonResponse
    {
        $data = $this->flightService->getLiveFlights();

        $query = trim((string) $request->query('q', ''));
        if ($query !== '') {
            $queryUpper = strtoupper($query);
            $data['flights'] = array_values(array_filter($data['flights'], function (array $f) use ($queryUpper): bool {
                return str_contains(strtoupper($f['callsign']), $queryUpper)
                    || str_contains(strtoupper($f['airline'] ?? ''), $queryUpper)
                    || str_contains(strtoupper($f['country']), $queryUpper);
            }));
            $data['total'] = count($data['flights']);
        }

        return response()->json($data);
    }

    /**
     * Endpoint API JSON daftar bandara untuk overlay di radar.
     */
    public function airports(): JsonResponse
    {
        return response()->json($this->flightService->getRadarAirports());
    }
}
