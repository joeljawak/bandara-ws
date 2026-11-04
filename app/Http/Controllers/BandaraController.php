<?php

namespace App\Http\Controllers;

use App\Services\AirportImageService;
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
            $k = FusekiService::esc($q);
            $filter .= 'FILTER(CONTAINS(LCASE(CONCAT(?nama, " ", ?iata, " ", COALESCE(?wilayah, ""), " ", COALESCE(?provinsi, ""))), LCASE("' . $k . '")))';
        }
        if ($negara !== '') {
            $filter .= ' FILTER(?negara = "' . FusekiService::esc($negara) . '")';
        }

        try {
            $negaraList = $fuseki->query(
                'SELECT DISTINCT ?negara WHERE {
                    ?a a ex:Airport ; ex:airportStatus ?s ; ex:locatedInCountry ?c .
                    ?c rdfs:label ?negara
                } ORDER BY ?negara'
            );

            $bandara = $fuseki->query(
                'SELECT ?iata ?nama ?negara ?status ?wilayah ?provinsi ?lat ?lon ?runway ?penumpang WHERE {
                    ?a a ex:Airport ; rdfs:label ?nama ; ex:hasIATACode ?iata ;
                       ex:airportStatus ?status ; ex:locatedInCountry ?c .
                    ?c rdfs:label ?negara .
                    OPTIONAL {
                        ?a ex:locatedInRegion ?r . ?r rdfs:label ?wilayah .
                        OPTIONAL { ?r ex:partOfRegion ?pr . ?pr rdfs:label ?provinsi }
                    }
                    OPTIONAL { ?a geo1:lat ?lat ; geo1:long ?lon }
                    OPTIONAL { ?a ex:longestRunwayLength ?runway }
                    OPTIONAL { ?a ex:annualPassengers ?penumpang }
                    ' . $filter . '
                } ORDER BY ?nama LIMIT 50'
            );
        } catch (\Throwable $e) {
            $error = 'Data bandara belum bisa diambil. Pastikan Fuseki sudah berjalan.';
        }

        $fotos = $bandara ? $images->photos(array_column($bandara, 'nama')) : [];

        return view('bandara.index', compact('bandara', 'negaraList', 'q', 'negara', 'error', 'fotos'));
    }

    public function show(string $iata, FusekiService $fuseki, AirportImageService $images, FlightService $flights)
    {
        $iata = strtoupper($iata);

        $info = $fuseki->query(
            'SELECT ?nama ?icao ?negara ?status ?kategori ?operator ?lat ?long
                    ?wilayah ?provinsi ?penumpang ?runway ?sameAs WHERE {
                ?a ex:hasIATACode "' . $iata . '" ; rdfs:label ?nama ; ex:locatedInCountry ?c .
                ?c rdfs:label ?negara .
                OPTIONAL { ?a ex:hasICAOCode ?icao }
                OPTIONAL { ?a ex:airportStatus ?status }
                OPTIONAL { ?a ex:airportCategory ?kategori }
                OPTIONAL { ?a ex:operator ?operator }
                OPTIONAL { ?a geo1:lat ?lat ; geo1:long ?long }
                OPTIONAL { ?a ex:annualPassengers ?penumpang }
                OPTIONAL { ?a ex:longestRunwayLength ?runway }
                OPTIONAL { ?a owl:sameAs ?sameAs }
                OPTIONAL {
                    ?a ex:locatedInRegion ?r . ?r rdfs:label ?wilayah .
                    OPTIONAL { ?r ex:partOfRegion ?pr . ?pr rdfs:label ?provinsi }
                }
            } LIMIT 1'
        );

        abort_if(empty($info), 404);

        $rute = $fuseki->query(
            'SELECT ?dst_iata ?dst_nama ?maskapai WHERE {
                ?a ex:hasIATACode "' . $iata . '" .
                ?r a ex:Route ; ex:origin ?a ; ex:destination ?d ; ex:operatedBy ?m .
                ?d ex:hasIATACode ?dst_iata ; rdfs:label ?dst_nama .
                ?m rdfs:label ?maskapai
            } ORDER BY ?dst_nama LIMIT 500'
        );

        $b = $info[0];

        return view('bandara.show', [
            'b' => $b,
            'iata' => $iata,
            'rute' => collect($rute)->groupBy('dst_iata'),
            'foto' => $images->photo($b['nama']),
            'jadwal' => $flights->departures($iata),
        ]);
    }
}
