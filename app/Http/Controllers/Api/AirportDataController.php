<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AirlineResource;
use App\Http\Resources\AirportResource;
use App\Http\Resources\RouteResource;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Country;
use App\Models\Region;
use App\Models\Route;
use App\Services\AirportSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class AirportDataController extends Controller
{
    public function airports(Request $request, AirportSearch $search): AnonymousResourceCollection
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $negara = trim((string) $request->query('negara', ''));
        $countryId = $request->integer('country') ?: null;
        $asean = $request->query('asean');
        $kategori = trim((string) $request->query('kategori', ''));
        $punyaRute = $request->query('rute');
        $urut = (string) $request->query('urut', 'relevan');
        $near = trim((string) $request->query('near', ''));
        $radius = max(1, min(2000, $request->integer('radius', 100)));
        $perPage = $this->perPage($request);

        $urutValid = in_array($urut, ['relevan', 'nama', 'penumpang'], true) ? $urut : 'relevan';

        if ($q !== '' || $near !== '' || $urutValid !== 'relevan') {
            $urut = $urutValid;
            $hasil = $search->cari([
                'q' => $q,
                'negara' => $negara !== '' ? $negara : $countryId,
                'kategori' => $kategori,
                'asean' => $asean,
                'rute' => $punyaRute,
                'urut' => $urut,
                'near' => $near,
                'radius' => $radius,
                'page' => $request->integer('page', 1),
                'per_page' => $perPage,
            ]);

            $iata = collect($hasil->items())->pluck('iata')->filter()->all();
            $model = Airport::query()
                ->with(['country', 'region'])
                ->withCount(['departureRoutes', 'arrivalRoutes'])
                ->whereIn('iata_code', $iata)
                ->get()
                ->keyBy('iata_code');

            $items = collect($iata)->map(fn (string $kode) => $model->get($kode))->filter()->values();

            return AirportResource::collection(new LengthAwarePaginator(
                $items,
                $hasil->total(),
                $hasil->perPage(),
                $hasil->currentPage(),
                ['path' => $request->url(), 'query' => $request->query()]
            ));
        }

        $airports = Airport::query()
            ->with(['country', 'region'])
            ->withCount(['departureRoutes', 'arrivalRoutes'])
            ->search($q)
            ->when($countryId, fn (Builder $query) => $query->where('country_id', $countryId))
            ->when($negara !== '', fn (Builder $query) => $query->whereHas('country', fn (Builder $negaraQuery) => $negaraQuery->where('label', $negara)))
            ->when($kategori !== '', fn (Builder $query) => $query->where('category', $kategori))
            ->when($asean === '1', fn (Builder $query) => $query->inAsean())
            ->when($asean === '0', fn (Builder $query) => $query->inAsean(false))
            ->when($punyaRute === '1', fn (Builder $query) => $query->has('departureRoutes'))
            ->orderBy('label')
            ->paginate($perPage)
            ->withQueryString();

        return AirportResource::collection($airports);
    }

    public function airport(Airport $airport): AirportResource
    {
        $airport->load(['country', 'region.parent', 'nearestAirport'])
            ->loadCount(['departureRoutes', 'arrivalRoutes', 'airlines', 'hubAirlines']);

        return new AirportResource($airport);
    }

    public function airlines(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('q', ''));
        $perPage = $this->perPage($request);

        $airlines = Airline::query()
            ->with('hubAirport.country')
            ->withCount(['routes', 'airports'])
            ->search($search)
            ->orderBy('label')
            ->paginate($perPage)
            ->withQueryString();

        return AirlineResource::collection($airlines);
    }

    public function routes(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('q', ''));
        $originId = $request->integer('origin') ?: null;
        $destinationId = $request->integer('destination') ?: null;
        $airlineId = $request->integer('airline') ?: null;
        $perPage = $this->perPage($request);

        $routes = Route::query()
            ->with(['originAirport.country', 'destinationAirport.country', 'airline'])
            ->when($originId, fn (Builder $query) => $query->where('origin_airport_id', $originId))
            ->when($destinationId, fn (Builder $query) => $query->where('destination_airport_id', $destinationId))
            ->when($airlineId, fn (Builder $query) => $query->where('airline_id', $airlineId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->whereHas('originAirport', fn (Builder $relation) => $relation
                        ->whereLike('label', $like)
                        ->orWhereLike('iata_code', $like))
                        ->orWhereHas('destinationAirport', fn (Builder $relation) => $relation
                            ->whereLike('label', $like)
                            ->orWhereLike('iata_code', $like))
                        ->orWhereHas('airline', fn (Builder $relation) => $relation
                            ->whereLike('label', $like)
                            ->orWhereLike('airline_code', $like));
                });
            })
            ->orderBy('origin_airport_id')
            ->paginate($perPage)
            ->withQueryString();

        return RouteResource::collection($routes);
    }

    public function stats(): JsonResponse
    {
        $topCountries = Country::query()
            ->withCount('airports')
            ->orderByDesc('airports_count')
            ->limit(10)
            ->get(['id', 'label'])
            ->map(fn (Country $country): array => [
                'country' => $country->label,
                'airports' => $country->airports_count,
            ]);

        $topAirlines = Airline::query()
            ->withCount('routes')
            ->orderByDesc('routes_count')
            ->limit(10)
            ->get(['id', 'label', 'airline_code'])
            ->map(fn (Airline $airline): array => [
                'airline' => $airline->label,
                'code' => $airline->airline_code,
                'routes' => $airline->routes_count,
            ]);

        return response()->json([
            'data' => [
                'countries' => Country::query()->count(),
                'regions' => Region::query()->count(),
                'airports' => Airport::query()->count(),
                'airports_with_coordinates' => Airport::query()->withCoordinates()->count(),
                'airports_in_asean' => Airport::query()->inAsean()->count(),
                'airlines' => Airline::query()->count(),
                'routes' => Route::query()->count(),
                'top_countries' => $topCountries,
                'top_airlines' => $topAirlines,
            ],
        ]);
    }

    private function perPage(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page', 25)));
    }
}
