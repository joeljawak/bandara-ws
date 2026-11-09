<?php

namespace App\Http\Controllers;

use App\Services\AirportImageService;
use App\Services\DbpediaService;
use App\Services\FlightService;
use App\Services\FusekiService;
use Illuminate\Http\Request;

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
            $filter .= 'FILTER(CONTAINS(LCASE(STR(?nama)), "' . $k . '") || LCASE(STR(?iata)) = "' . $k . '" || CONTAINS(LCASE(STR(?kota)), "' . $k . '") || CONTAINS(LCASE(STR(?negara)), "' . $k . '"))';
        }
        if ($negara !== '') {
            $filter .= ' FILTER(LCASE(STR(?negara)) = LCASE("' . FusekiService::esc($negara) . '"))';
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
                    ' . $filter . '
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
                ?a a ex:Bandara ; ex:kodeIATA "' . FusekiService::esc($iata) . '" ;
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

        // Dataset bandara_asean.ttl yang diberikan belum memuat relasi rute penerbangan.
        $rute = [];

        $b = $info[0];
        $foto = $images->photo($b['nama'], $iata);

        return view('bandara.show', [
            'b' => $b,
            'iata' => $iata,
            'rute' => collect($rute)->groupBy('dst_iata'),
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
            return 'http://dbpedia.org/resource/' . rawurldecode($m[1]);
        }

        return null;
    }
}
