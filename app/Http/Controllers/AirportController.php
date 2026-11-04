<?php

namespace App\Http\Controllers;

use App\Services\AirportImageService;
use App\Services\SparqlService;
use Illuminate\Http\Request;

class AirportController extends Controller
{
    public function __construct(private SparqlService $sparql, private AirportImageService $images)
    {
    }

    public function home()
    {
        return view('home');
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $results = [];
        $error = null;

        if ($q !== '') {
            try {
                $results = $this->sparql->search($q);
            } catch (\Throwable $e) {
                $error = 'Data bandara belum bisa diambil. Pastikan Fuseki sudah berjalan.';
            }
        }

        $fotos = $results ? $this->images->photos(array_column($results, 'nama')) : [];

        return view('results', compact('q', 'results', 'error', 'fotos'));
    }
}
