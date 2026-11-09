<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AirlineResource extends JsonResource
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
            'airline_code' => $this->airline_code,
            'hub_airport' => $this->whenLoaded('hubAirport', fn (): ?array => $this->hubAirport === null ? null : [
                'id' => $this->hubAirport->id,
                'label' => $this->hubAirport->label,
                'iata_code' => $this->hubAirport->iata_code,
                'country' => $this->hubAirport->relationLoaded('country') && $this->hubAirport->country !== null
                    ? $this->hubAirport->country->label
                    : null,
            ]),
            'website' => $this->website,
            'dbpedia_url' => $this->dbpedia_url,
            'wikidata_url' => $this->wikidata_url,
            'routes_count' => $this->whenCounted('routes'),
            'airports_count' => $this->whenCounted('airports'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
