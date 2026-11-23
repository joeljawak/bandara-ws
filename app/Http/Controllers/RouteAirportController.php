<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Route;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RouteAirportController extends Controller
{
    public function index(Request $request)
    {
        $bandara = Airport::query()
            ->withCoordinates()
            ->orderBy('label')
            ->get(['iata_code', 'label', 'latitude', 'longitude'])
            ->pipe(fn ($items) => $this->filter($items, fn (Airport $airport): bool => $airport->iata_code !== null))
            ->map(fn (Airport $airport): array => [
                'iata' => $airport->iata_code,
                'nama' => $airport->label,
                'lat' => $airport->latitude,
                'lon' => $airport->longitude,
            ])
            ->values();

        $mode = $request->input('mode') === 'lokasi' ? 'lokasi' : 'bandara';
        $dari = strtoupper(trim((string) $request->input('dari', '')));
        $ke = strtoupper(trim((string) $request->input('ke', '')));
        $asal = $tujuan = null;
        $lokasiAsal = null;
        $jarak = null;
        $error = null;

        if ($mode === 'lokasi') {
            $lat = $request->input('lat');
            $lon = $request->input('lon');

            if ($lat === null || $lon === null || ! is_numeric($lat) || ! is_numeric($lon)
                || (float) $lat < -90 || (float) $lat > 90
                || (float) $lon < -180 || (float) $lon > 180) {
                $error = 'Lokasi belum tersedia. Tekan tombol Gunakan lokasi saya dan izinkan akses lokasi di browser.';
            } else {
                $lokasiAsal = [
                    'lat' => (float) $lat,
                    'lon' => (float) $lon,
                    'nama' => 'Lokasi saya',
                ];
            }

            $tujuan = $ke !== '' ? $bandara->firstWhere('iata', $ke) : null;

            if ($lokasiAsal && $tujuan) {
                $jarak = (int) round(Geo::jarakKm(
                    $lokasiAsal['lat'],
                    $lokasiAsal['lon'],
                    (float) $tujuan['lat'],
                    (float) $tujuan['lon']
                ));
            } elseif ($ke !== '' && ! $tujuan) {
                $error = 'Bandara tujuan tidak ditemukan atau belum memiliki koordinat.';
            }

            // Mode lokasi adalah perjalanan darat menuju bandara, bukan pencarian jadwal/rute penerbangan.
            $langsung = collect();
            $transit = collect();
        } else {
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
        }

        return view('route', [
            'bandara' => $bandara,
            'mode' => $mode,
            'dari' => $dari,
            'ke' => $ke,
            'asal' => $asal,
            'lokasiAsal' => $lokasiAsal,
            'tujuan' => $tujuan,
            'jarak' => $jarak,
            'langsung' => $langsung,
            'transit' => $transit,
            'error' => $error,
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
