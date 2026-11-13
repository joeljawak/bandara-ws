<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Country;
use App\Models\Route;
use App\Services\AirportImageService;
use App\Services\AirportSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AirportController extends Controller
{
    public function __construct(private AirportSearch $search, private AirportImageService $images) {}

    public function home()
    {
        $stats = Cache::remember('beranda.stats', now()->addMinutes(10), fn (): array => [
            'airports' => Airport::query()->count(),
            'airlines' => Airline::query()->count(),
            'routes' => Route::query()->count(),
            'countries' => Country::query()->count(),
        ]);

        return view('home', compact('stats'));
    }

    public function search(Request $request)
    {
        $q = $this->kataKunci($request);
        $jenis = (string) $request->query('jenis', 'semua');
        $jenis = in_array($jenis, ['semua', 'bandara', 'maskapai', 'rute'], true) ? $jenis : 'semua';
        $near = trim((string) $request->query('near', ''));
        $radius = max(1, min(2000, (int) $request->query('radius', 100)));
        $urut = (string) $request->query('urut', 'relevan');
        $urut = in_array($urut, ['relevan', 'nama', 'penumpang'], true) ? $urut : 'relevan';

        $opsi = [
            'q' => $q,
            'negara' => $request->query('negara'),
            'kategori' => $request->query('kategori'),
            'asean' => $request->query('asean'),
            'rute' => $request->query('rute'),
            'urut' => $urut,
            'near' => $near,
            'radius' => $radius,
            'page' => $request->query('page', 1),
            'per_page' => 15,
        ];

        $results = $this->search->cari($opsi);

        $fotos = [];
        if ($results->isNotEmpty()) {
            try {
                $fotos = $this->images->photos(array_map(
                    fn (array $row): array => ['nama' => $row['nama'], 'iata' => $row['iata']],
                    $results->items()
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $maskapai = in_array($jenis, ['semua', 'maskapai'], true) ? $this->search->maskapai($q) : collect();
        $rute = in_array($jenis, ['semua', 'rute'], true) ? $this->search->rute($q) : collect();

        $saran = ($q !== '' && $results->total() < 3)
            ? $this->search->saran($q, 3)
            : [];

        return view('results', [
            'q' => $q,
            'results' => $results,
            'fotos' => $fotos,
            'error' => null,
            'jenis' => $jenis,
            'maskapai' => $maskapai,
            'rute' => $rute,
            'saran' => $saran,
            'negara' => (string) $request->query('negara', ''),
            'kategori' => (string) $request->query('kategori', ''),
            'asean' => (string) $request->query('asean', ''),
            'ruteFilter' => (string) $request->query('rute', ''),
            'urut' => $urut,
            'near' => $near,
            'radius' => $radius,
            'facets' => $this->facets(),
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $q = $this->kataKunci($request);

        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $limit = max(1, min(8, (int) $request->query('limit', 8)));
        $key = 'saran.'.md5(mb_strtolower($q)).'.'.$limit;

        $data = Cache::remember($key, now()->addSeconds(60), fn (): array => $this->search->saran($q, $limit));

        return response()->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=30');
    }

    private function kataKunci(Request $request): string
    {
        $q = preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $request->query('q', '')) ?? '';

        return mb_substr(trim($q), 0, 80);
    }

    /**
     * @return array{
     *     countries: list<array{label: string, airports_count: int}>,
     *     categories: list<string>
     * }
     */
    private function facets(): array
    {
        return Cache::remember('facets.pencarian.v2', now()->addMinutes(10), fn (): array => [
            'countries' => Country::query()
                ->withCount('airports')
                ->whereHas('airports')
                ->orderBy('label')
                ->get(['id', 'label'])
                ->map(fn (Country $country): array => [
                    'label' => $country->label,
                    'airports_count' => (int) $country->airports_count,
                ])
                ->all(),
            'categories' => Airport::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->all(),
        ]);
    }
}
