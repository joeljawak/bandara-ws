<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Country;
use App\Models\Route;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AirportDataController extends Controller
{
    public function airports(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $countryId = $request->integer('country') ?: null;
        $asean = $request->query('asean');

        $airports = Airport::query()
            ->with(['country', 'region'])
            ->search($search)
            ->when($countryId, fn (Builder $query) => $query->where('country_id', $countryId))
            ->when($asean === '1', fn (Builder $query) => $query->inAsean())
            ->when($asean === '0', fn (Builder $query) => $query->inAsean(false))
            ->orderBy('label')
            ->paginate(30)
            ->withQueryString();

        return view('data.airports', [
            'airports' => $airports,
            'countries' => Country::query()->orderBy('label')->get(['id', 'label']),
            'search' => $search,
            'countryId' => $countryId,
            'asean' => $asean,
            'total' => Airport::query()->count(),
        ]);
    }

    public function airport(Airport $airport): RedirectResponse
    {
        // Satu URL kanonik per entitas: halaman detail utama ada di /bandara/{iata}.
        return redirect()->route('bandara.show', $airport->iata_code, 301);
    }

    public function airlines(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $airlines = Airline::query()
            ->with('hubAirport.country')
            ->withCount(['routes', 'airports'])
            ->search($search)
            ->orderBy('label')
            ->paginate(30)
            ->withQueryString();

        return view('data.airlines', [
            'airlines' => $airlines,
            'search' => $search,
            'total' => Airline::query()->count(),
        ]);
    }

    public function routes(Request $request): View
    {
        $originId = $request->integer('origin') ?: null;
        $destinationId = $request->integer('destination') ?: null;
        $airlineId = $request->integer('airline') ?: null;
        $search = trim((string) $request->query('q', ''));

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
            ->paginate(30)
            ->withQueryString();

        return view('data.routes', [
            'routes' => $routes,
            'airports' => Airport::query()->orderBy('label')->get(['id', 'label', 'iata_code']),
            'airlines' => Airline::query()->orderBy('label')->get(['id', 'label', 'airline_code']),
            'originId' => $originId,
            'destinationId' => $destinationId,
            'airlineId' => $airlineId,
            'search' => $search,
            'total' => Route::query()->count(),
        ]);
    }

    public function map(Request $request): View
    {
        $asean = $request->query('asean');

        $airports = Airport::query()
            ->with(['country', 'region'])
            ->withCoordinates()
            ->when($asean === '1', fn (Builder $query) => $query->inAsean())
            ->when($asean === '0', fn (Builder $query) => $query->inAsean(false))
            ->orderBy('label')
            ->get()
            ->map(fn (Airport $airport): array => [
                'id' => $airport->id,
                'label' => $airport->label,
                'iata' => $airport->iata_code,
                'latitude' => (float) $airport->latitude,
                'longitude' => (float) $airport->longitude,
                'country' => $airport->country?->label,
                'region' => $airport->region?->label,
                'in_asean' => (bool) $airport->in_asean,
                'url' => $airport->iata_code !== null
                    ? route('data.airport', $airport->iata_code)
                    : null,
            ])
            ->values();

        return view('data.map', [
            'airports' => $airports,
            'asean' => $asean,
            'total' => Airport::query()->withCoordinates()->count(),
        ]);
    }
}
