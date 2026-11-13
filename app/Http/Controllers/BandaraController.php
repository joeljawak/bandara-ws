<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Route;
use App\Services\AirportImageService;
use App\Services\AirportSearch;
use App\Services\DbpediaService;
use App\Services\FlightService;
use Illuminate\Http\Request;

class BandaraController extends Controller
{
    public function index(Request $request, AirportSearch $search, AirportImageService $images)
    {
        $bandara = $search->cari([
            'q' => $request->query('q'),
            'negara' => $request->query('negara'),
            'kategori' => $request->query('kategori'),
            'asean' => $request->query('asean'),
            'rute' => $request->query('rute'),
            'urut' => $request->query('urut'),
            'page' => $request->query('page'),
        ]);

        $negaraList = Country::query()
            ->withCount('airports')
            ->whereHas('airports')
            ->orderBy('label')
            ->get(['id', 'label']);

        $kategoriList = Airport::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $fotos = [];
        if ($bandara->isNotEmpty()) {
            try {
                $fotos = $images->photos(array_map(
                    fn (array $row): array => ['nama' => $row['nama'], 'iata' => $row['iata']],
                    $bandara->items()
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return view('bandara.index', [
            'bandara' => $bandara,
            'negaraList' => $negaraList,
            'kategoriList' => $kategoriList,
            'q' => (string) $request->query('q', ''),
            'negara' => (string) $request->query('negara', ''),
            'kategori' => (string) $request->query('kategori', ''),
            'asean' => (string) $request->query('asean', ''),
            'rute' => (string) $request->query('rute', ''),
            'urut' => (string) $request->query('urut', 'relevan'),
            'error' => null,
            'fotos' => $fotos,
        ]);
    }

    public function show(
        string $iata,
        AirportImageService $images,
        FlightService $flights,
        DbpediaService $dbpedia
    ) {
        $iata = strtoupper($iata);

        $airport = Airport::query()
            ->with([
                'country',
                'region.parent',
                'nearestAirport',
                'hubAirlines',
                'airlines',
            ])
            ->withCount(['departureRoutes', 'arrivalRoutes'])
            ->where('iata_code', $iata)
            ->firstOrFail();

        $rute = $airport->departureRoutes()
            ->with(['destinationAirport.country', 'airline'])
            ->get()
            ->map(fn (Route $route): array => [
                'dst_iata' => $route->destinationAirport?->iata_code,
                'dst_nama' => $route->destinationAirport?->label,
                'maskapai' => $route->airline?->label,
            ])
            ->filter(fn (array $row): bool => ! empty($row['dst_iata']))
            ->groupBy('dst_iata');

        $b = [
            'nama' => $airport->label,
            'negara' => $airport->country?->label,
            'wilayah' => $airport->region?->label,
            'provinsi' => $airport->region?->parent?->label,
            'icao' => $airport->icao_code,
            'status' => $airport->status,
            'kategori' => $airport->category,
            'operator' => $airport->operator,
            'penumpang' => $airport->annual_passengers,
            'runway' => $airport->longest_runway_length,
            'tahun' => $airport->opened_year,
            'lat' => $airport->latitude,
            'long' => $airport->longitude,
            'website' => $airport->website,
            'sameAs' => $airport->dbpedia_url,
        ];

        $foto = $images->photo($airport->label, $iata);

        return view('bandara.show', [
            'airport' => $airport,
            'b' => $b,
            'iata' => $iata,
            'rute' => $rute,
            'foto' => $foto,
            'jadwal' => $flights->departures($iata),
            'dbpedia' => $dbpedia->describe($this->alamatDbpedia($b, $foto)),
        ]);
    }

    /**
     * Pakai tautan owl:sameAs dari data; kalau belum ada, turunkan dari halaman
     * Wikipedia yang dipakai untuk foto.
     *
     * @param  array<string, mixed>  $b
     * @param  array{url: string, page: string}|null  $foto
     */
    private function alamatDbpedia(array $b, ?array $foto): ?string
    {
        $sama = $b['sameAs'] ?? '';

        if ($sama !== '' && DbpediaService::valid(DbpediaService::normalisasi($sama))) {
            return $sama;
        }

        if (preg_match('#^https://en\.wikipedia\.org/wiki/(.+)$#', $foto['page'] ?? '', $m)) {
            return 'http://dbpedia.org/resource/'.rawurldecode($m[1]);
        }

        return null;
    }
}
