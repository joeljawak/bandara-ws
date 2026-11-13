<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Route;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RouteAirportController extends Controller
{
    public function index(Request $request)
    {
        $bandara = Airport::query()
            ->withCoordinates()
            ->orderBy('label')
            ->get(['iata_code', 'label', 'latitude', 'longitude'])
            ->filter(fn (Airport $airport): bool => $airport->iata_code !== null)
            ->map(fn (Airport $airport): array => [
                'iata' => $airport->iata_code,
                'nama' => $airport->label,
                'lat' => $airport->latitude,
                'lon' => $airport->longitude,
            ])
            ->values();

        $dari = strtoupper(trim((string) $request->query('dari', '')));
        $ke = strtoupper(trim((string) $request->query('ke', '')));
        $asal = $tujuan = null;
        $jarak = null;

        if ($dari && $ke && $dari !== $ke) {
            $asal = $bandara->firstWhere('iata', $dari);
            $tujuan = $bandara->firstWhere('iata', $ke);

            if ($asal && $tujuan) {
                $jarak = (int) round(Geo::jarakKm(
                    (float) $asal['lat'],
                    (float) $asal['lon'],
                    (float) $tujuan['lat'],
                    (float) $tujuan['lon']
                ));
            }
        }

        $langsung = ($asal && $tujuan) ? $this->ruteLangsung($dari, $ke) : collect();
        $transit = ($asal && $tujuan && $langsung->isEmpty()) ? $this->ruteTransit($dari, $ke) : collect();

        return view('route', [
            'bandara' => $bandara,
            'dari' => $dari,
            'ke' => $ke,
            'asal' => $asal,
            'tujuan' => $tujuan,
            'jarak' => $jarak,
            'langsung' => $langsung,
            'transit' => $transit,
            'error' => null,
        ]);
    }

    /**
     * @return Collection<int, Route>
     */
    private function ruteLangsung(string $dari, string $ke): Collection
    {
        return Route::query()
            ->with(['airline', 'originAirport', 'destinationAirport'])
            ->whereHas('originAirport', fn ($query) => $query->where('iata_code', $dari))
            ->whereHas('destinationAirport', fn ($query) => $query->where('iata_code', $ke))
            ->get();
    }

    /**
     * Rute dengan maksimal satu kali transit (BFS dua langkah).
     *
     * @return Collection<int, array{transit: string, maskapai: array<int, string>}>
     */
    private function ruteTransit(string $dari, string $ke): Collection
    {
        $keluar = Route::query()
            ->with(['airline', 'destinationAirport'])
            ->whereHas('originAirport', fn ($query) => $query->where('iata_code', $dari))
            ->get();

        $masuk = Route::query()
            ->with('airline')
            ->whereHas('destinationAirport', fn ($query) => $query->where('iata_code', $ke))
            ->get()
            ->groupBy(fn (Route $route): int => (int) $route->origin_airport_id);

        $hasil = [];

        foreach ($keluar as $awal) {
            $lanjutan = $masuk->get((int) $awal->destination_airport_id);

            if (! $lanjutan) {
                continue;
            }

            $iata = $awal->destinationAirport?->iata_code;

            if ($iata === null || $iata === $ke || $iata === $dari) {
                continue;
            }

            $hasil[$iata] = [
                'transit' => $iata,
                'nama' => $awal->destinationAirport?->label ?? $iata,
                'maskapai' => $lanjutan->pluck('airline.label')->filter()->unique()->values()->all(),
            ];
        }

        return collect(array_values($hasil))->sortBy('nama')->values();
    }
}
