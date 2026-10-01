<?php

namespace App\Http\Controllers;

use App\Services\SparqlService;
use Illuminate\Http\Request;

class RouteAirportController extends Controller
{
    public function __construct(private SparqlService $sparql)
    {
    }

    public function index(Request $request)
    {
        try {
            $bandara = $this->sparql->allWithCoords();
            $error = null;
        } catch (\Throwable $e) {
            $bandara = [];
            $error = 'Data bandara belum bisa diambil.';
        }

        $dari = $request->query('dari');
        $ke = $request->query('ke');
        $asal = $tujuan = null;
        $jarak = null;

        if ($dari && $ke && $dari !== $ke) {
            $asal = collect($bandara)->firstWhere('iata', $dari);
            $tujuan = collect($bandara)->firstWhere('iata', $ke);
            if ($asal && $tujuan) {
                $jarak = $this->jarakKm((float) $asal['lat'], (float) $asal['lon'], (float) $tujuan['lat'], (float) $tujuan['lon']);
            }
        }

        return view('route', compact('bandara', 'dari', 'ke', 'asal', 'tujuan', 'jarak', 'error'));
    }

    // jarak garis lurus antar dua koordinat (formula haversine), dalam km
    private function jarakKm(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $r = 6371;
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $dLat = $lat2 - $lat1;
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
        return (int) round(2 * $r * asin(sqrt($a)));
    }
}
