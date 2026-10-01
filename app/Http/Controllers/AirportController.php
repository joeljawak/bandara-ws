<?php

namespace App\Http\Controllers;

use App\Services\AirportImageService;
use App\Services\FlightService;
use App\Services\SparqlService;
use Illuminate\Http\Request;

class AirportController extends Controller
{
    public function __construct(
        private SparqlService $sparql,
        private FlightService $flights,
        private AirportImageService $images,
    ) {
    }

    public function home()
    {
        return view('home');
    }

    public function search(Request $request)
    {
        $q = trim($request->query('q', ''));
        $results = [];
        $error = null;

        if ($q !== '') {
            try {
                $results = $this->sparql->search($q);
            } catch (\Throwable $e) {
                $error = 'Data bandara belum bisa diambil. Pastikan Fuseki sudah berjalan.';
            }
        }

        return view('results', compact('q', 'results', 'error'));
    }

    public function show(string $iata)
    {
        try {
            $airport = $this->sparql->find($iata);
        } catch (\Throwable $e) {
            abort(503, 'Fuseki belum berjalan.');
        }
        abort_if(!$airport, 404, 'Bandara tidak ditemukan.');

        $airlines = $this->sparql->airlines($iata);
        $flights = $this->flights->departures(strtoupper($iata));
        $foto = $this->images->thumbnail($airport['nama']);

        return view('detail', compact('airport', 'airlines', 'flights', 'foto'));
    }
}
