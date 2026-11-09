<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteResource extends JsonResource
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
            'origin' => $this->whenLoaded('originAirport', fn (): ?array => $this->originAirport === null ? null : [
                'id' => $this->originAirport->id,
                'label' => $this->originAirport->label,
                'iata_code' => $this->originAirport->iata_code,
                'country' => $this->originAirport->relationLoaded('country') && $this->originAirport->country !== null
                    ? $this->originAirport->country->label
                    : null,
            ]),
            'destination' => $this->whenLoaded('destinationAirport', fn (): ?array => $this->destinationAirport === null ? null : [
                'id' => $this->destinationAirport->id,
                'label' => $this->destinationAirport->label,
                'iata_code' => $this->destinationAirport->iata_code,
                'country' => $this->destinationAirport->relationLoaded('country') && $this->destinationAirport->country !== null
                    ? $this->destinationAirport->country->label
                    : null,
            ]),
            'airline' => $this->whenLoaded('airline', fn (): ?array => $this->airline === null ? null : [
                'id' => $this->airline->id,
                'label' => $this->airline->label,
                'airline_code' => $this->airline->airline_code,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
