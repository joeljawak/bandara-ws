<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Region;
use App\Models\Route;
use App\Services\AirportImageService;
use App\Services\AirportSparqlSearch;
use App\Services\AirportSearch;
use App\Services\DbpediaService;
use App\Services\FlightService;
use App\Support\Geo;
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

        if (config('sparql.live')) {
            $facets = app(AirportSparqlSearch::class)->facets();
            $negaraList = collect($facets['countries']);
            $kategoriList = collect($facets['categories']);
        } else {
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
        }

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
        DbpediaService $dbpedia,
        Request $request
    ) {
        $iata = strtoupper($iata);
        $arahJadwal = $request->query('jadwal') === 'kedatangan' ? 'kedatangan' : 'keberangkatan';

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

        $masuk = $airport->arrivalRoutes()
            ->with(['originAirport', 'airline'])
            ->get()
            ->map(fn (Route $route): array => [
                'src_iata' => $route->originAirport?->iata_code,
                'src_nama' => $route->originAirport?->label,
                'maskapai' => $route->airline?->label,
            ])
            ->filter(fn (array $row): bool => ! empty($row['src_iata']))
            ->groupBy('src_iata');

        $dekat = $airport->nearestAirport;

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
            'wikidata' => $airport->wikidata_url,
            'nama_lokal' => $airport->local_name,
            'elevasi' => $airport->elevation,
            'jarak_kota' => $airport->distance_to_city_center_km,
            'jarak_terdekat' => $airport->distance_to_nearest_airport_km,
            'terdekat' => $dekat ? ['nama' => $dekat->label, 'iata' => $dekat->iata_code] : null,
            'penumpang_puncak' => $airport->peak_annual_passengers,
            'landasan_lebar' => $airport->longest_runway_width,
            'landasan_jumlah' => $airport->runway_count,
            'landasan_nama' => $airport->runway_designation,
            'permukaan' => $airport->surface_material,
            'jml_maskapai' => $airport->number_of_airlines,
            'tujuan' => $airport->number_of_destinations,
            'tujuan_asean' => $airport->number_of_destinations_asean,
            'tujuan_luar' => $airport->number_of_destinations_outside_asean,
        ];

        $foto = $images->photo($airport->label, $iata);

        return view('bandara.show', [
            'airport' => $airport,
            'b' => $b,
            'iata' => $iata,
            'rute' => $rute,
            'masuk' => $masuk,
            'foto' => $foto,
            'jadwal' => $arahJadwal === 'kedatangan' ? $flights->arrivals($iata) : $flights->departures($iata),
            'arahJadwal' => $arahJadwal,
            'serupa' => $this->bandaraSerupa($airport),
            'dbpedia' => $dbpedia->describe($this->alamatDbpedia($b, $foto)),
        ]);
    }

    /**
     * Bandara lain di provinsi/wilayah yang sama. Kalau data wilayahnya kosong
     * atau tidak ada bandara lain di sana, pakai bandara terdekat di negara yang sama.
     *
     * @return array{judul: string, daftar: \Illuminate\Support\Collection<int, array<string, mixed>>}
     */
    private function bandaraSerupa(Airport $airport, int $batas = 6): array
    {
        $punyaKoordinat = $airport->latitude !== null && $airport->longitude !== null;
        $kolom = ['id', 'label', 'iata_code', 'category', 'annual_passengers', 'latitude', 'longitude', 'country_id', 'region_id'];

        $judul = null;
        $kandidat = collect();

        if ($airport->region_id && $airport->region) {
            $indukId = $airport->region->parent_id;
            $idWilayah = $indukId
                ? Region::query()->where('parent_id', $indukId)->pluck('id')->push($indukId)->push($airport->region_id)
                : collect([$airport->region_id]);

            $kandidat = Airport::query()
                ->select($kolom)
                ->where('id', '!=', $airport->id)
                ->whereNotNull('iata_code')
                ->whereIn('region_id', $idWilayah->unique()->values())
                ->get();

            $nama = $airport->region->parent?->label ?? $airport->region->label;
            $judul = $nama ? 'Bandara lain di '.$nama : null;
        }

        if ($kandidat->isEmpty() && $airport->country_id) {
            $kandidat = Airport::query()
                ->select($kolom)
                ->where('id', '!=', $airport->id)
                ->whereNotNull('iata_code')
                ->where('country_id', $airport->country_id)
                ->get();
            $judul = 'Bandara terdekat lainnya';
        }

        $daftar = $kandidat->map(function (Airport $a) use ($airport, $punyaKoordinat): array {
            $jarak = $punyaKoordinat && $a->latitude !== null && $a->longitude !== null
                ? Geo::jarakKm((float) $airport->latitude, (float) $airport->longitude, (float) $a->latitude, (float) $a->longitude)
                : null;

            return [
                'iata' => $a->iata_code,
                'nama' => $a->label,
                'kategori' => $a->category,
                'penumpang' => $a->annual_passengers,
                'jarak' => $jarak,
            ];
        });

        $daftar = $punyaKoordinat
            ? $daftar->sortBy(fn (array $r) => $r['jarak'] ?? PHP_FLOAT_MAX)
            : $daftar->sortByDesc(fn (array $r) => $r['penumpang'] ?? 0);

        return [
            'judul' => $judul ?? 'Bandara lain di sekitar sini',
            'daftar' => $daftar->take($batas)->values(),
        ];
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
