<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AirportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'label' => $this->label,
            'iata_code' => $this->iata_code,
            'icao_code' => $this->icao_code,
            'category' => $this->category,
            'status' => $this->status,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'elevation' => $this->elevation,
            'in_asean' => (bool) $this->in_asean,
            'country' => $this->whenLoaded('country', fn (): ?array => $this->country === null ? null : [
                'id' => $this->country->id,
                'label' => $this->country->label,
                'member_of_asean' => (bool) $this->country->member_of_asean,
            ]),
            'region' => $this->whenLoaded('region', fn (): ?array => $this->region === null ? null : [
                'id' => $this->region->id,
                'label' => $this->region->label,
                'admin_level' => $this->region->admin_level,
                'parent' => $this->region->relationLoaded('parent') && $this->region->parent !== null
                    ? ['id' => $this->region->parent->id, 'label' => $this->region->parent->label]
                    : null,
            ]),
            'nearest_airport' => $this->whenLoaded('nearestAirport', fn (): ?array => $this->nearestAirport === null ? null : [
                'id' => $this->nearestAirport->id,
                'label' => $this->nearestAirport->label,
                'iata_code' => $this->nearestAirport->iata_code,
            ]),
            'distance_to_city_center_km' => $this->distance_to_city_center_km,
            'distance_to_nearest_airport_km' => $this->distance_to_nearest_airport_km,
            'longest_runway_length' => $this->longest_runway_length,
            'longest_runway_width' => $this->longest_runway_width,
            'runway_count' => $this->runway_count,
            'runway_designation' => $this->runway_designation,
            'surface_material' => $this->surface_material,
            'operator' => $this->operator,
            'annual_passengers' => $this->annual_passengers,
            'peak_annual_passengers' => $this->peak_annual_passengers,
            'number_of_airlines' => $this->number_of_airlines,
            'number_of_destinations' => $this->number_of_destinations,
            'number_of_destinations_asean' => $this->number_of_destinations_asean,
            'number_of_destinations_outside_asean' => $this->number_of_destinations_outside_asean,
            'local_name' => $this->local_name,
            'website' => $this->website,
            'opened_year' => $this->opened_year,
            'dbpedia_url' => $this->dbpedia_url,
            'wikidata_url' => $this->wikidata_url,
            'departure_routes_count' => $this->whenCounted('departureRoutes'),
            'arrival_routes_count' => $this->whenCounted('arrivalRoutes'),
            'airlines_count' => $this->whenCounted('airlines'),
            'hub_airlines_count' => $this->whenCounted('hubAirlines'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
