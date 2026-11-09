<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Services\AirportImageService;
use App\Services\DbpediaService;
use App\Services\FlightService;
use App\Services\FusekiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class BandaraController extends Controller
{
    public function index(Request $request, FusekiService $fuseki, AirportImageService $images)
    {
        $q = trim((string) $request->input('q', ''));
        $negara = trim((string) $request->input('negara', ''));
        $error = null;
        $negaraList = [];
        $bandara = [];

        $filter = '';
        if ($q !== '') {
            $k = FusekiService::esc(mb_strtolower($q));
            $filter .= 'FILTER(CONTAINS(LCASE(STR(?nama)), "'.$k.'") || LCASE(STR(?iata)) = "'.$k.'" || CONTAINS(LCASE(STR(?kota)), "'.$k.'") || CONTAINS(LCASE(STR(?negara)), "'.$k.'"))';
        }
        if ($negara !== '') {
            $filter .= ' FILTER(LCASE(STR(?negara)) = LCASE("'.FusekiService::esc($negara).'"))';
        }

        try {
            $negaraList = $fuseki->query(
                'SELECT DISTINCT ?negara WHERE {
                    ?a a ex:Bandara ; ex:negara ?negara .
                } ORDER BY ?negara'
            );

            $bandara = $fuseki->query(
                'SELECT ?iata ?nama ?negara ?status ?wilayah ?provinsi ?lat ?lon ?runway ?penumpang WHERE {
                    ?a a ex:Bandara ; ex:namaBandara ?nama ; ex:kodeIATA ?iata ; ex:negara ?negara .
                    OPTIONAL { ?a ex:terletakDi ?k . ?k ex:namaKota ?wilayah }
                    OPTIONAL { ?a ex:latitude ?lat }
                    OPTIONAL { ?a ex:longitude ?lon }
                    OPTIONAL { ?a ex:kategori ?status }
                    '.$filter.'
                } ORDER BY ?nama LIMIT 50'
            );
        } catch (\Throwable $e) {
            report($e);
            $error = 'Data bandara belum bisa diambil. Periksa endpoint dan dataset Fuseki.';
        }

        $fotos = $bandara ? $images->photos(array_map(fn ($r) => ['nama' => $r['nama'], 'iata' => $r['iata']], $bandara)) : [];

        return view('bandara.index', compact('bandara', 'negaraList', 'q', 'negara', 'error', 'fotos'));
    }

    public function show(string $iata, FusekiService $fuseki, AirportImageService $images, FlightService $flights, DbpediaService $dbpedia)
    {
        $iata = strtoupper($iata);

        $info = $fuseki->query(
            'SELECT ?nama ?icao ?negara ?status ?kategori ?operator ?lat ?long
                    ?wilayah ?provinsi ?penumpang ?runway ?sameAs ?website ?tahun WHERE {
                ?a a ex:Bandara ; ex:kodeIATA "'.FusekiService::esc($iata).'" ;
                   ex:namaBandara ?nama ; ex:negara ?negara .
                OPTIONAL { ?a ex:kodeICAO ?icao }
                OPTIONAL { ?a ex:kategori ?kategori }
                OPTIONAL { ?a ex:latitude ?lat }
                OPTIONAL { ?a ex:longitude ?long }
                OPTIONAL { ?a ex:terletakDi ?k . ?k ex:namaKota ?wilayah }
                OPTIONAL { ?a ex:dilayaniOleh ?m . ?m ex:namaMaskapai ?operator }
            } LIMIT 1'
        );

        abort_if(empty($info), 404);

        // Rute diambil dari tabel relasional hasil impor bandara.ttl.
        $rute = collect();

        if (Schema::hasTable('routes')) {
            $rute = Route::query()
                ->with(['destinationAirport', 'airline'])
                ->whereHas('originAirport', fn (Builder $query) => $query->where('iata_code', $iata))
                ->get()
                ->map(fn (Route $route): array => [
                    'dst_iata' => $route->destinationAirport?->iata_code,
                    'dst_nama' => $route->destinationAirport?->label,
                    'maskapai' => $route->airline?->label,
                ])
                ->filter(fn (array $row): bool => $row['dst_iata'] !== null)
                ->groupBy('dst_iata');
        }

        $b = $info[0];
        $foto = $images->photo($b['nama'], $iata);

        return view('bandara.show', [
            'b' => $b,
            'iata' => $iata,
            'rute' => $rute,
            'foto' => $foto,
            'jadwal' => $flights->departures($iata),
            'dbpedia' => $dbpedia->describe($this->alamatDbpedia($b, $foto)),
        ]);
    }

    // pakai tautan owl:sameAs dari data; kalau belum ada, turunkan dari halaman Wikipedia yang dipakai untuk foto
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
