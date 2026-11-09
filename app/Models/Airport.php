<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Airport extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'external_id',
        'label',
        'iata_code',
        'icao_code',
        'category',
        'status',
        'latitude',
        'longitude',
        'elevation',
        'country_id',
        'region_id',
        'in_asean',
        'nearest_airport_id',
        'distance_to_city_center_km',
        'distance_to_nearest_airport_km',
        'longest_runway_length',
        'longest_runway_width',
        'runway_count',
        'runway_designation',
        'surface_material',
        'operator',
        'annual_passengers',
        'peak_annual_passengers',
        'number_of_airlines',
        'number_of_destinations',
        'number_of_destinations_asean',
        'number_of_destinations_outside_asean',
        'local_name',
        'website',
        'opened_year',
        'dbpedia_url',
        'wikidata_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'elevation' => 'float',
            'in_asean' => 'boolean',
            'distance_to_city_center_km' => 'float',
            'distance_to_nearest_airport_km' => 'float',
            'longest_runway_length' => 'float',
            'longest_runway_width' => 'float',
            'runway_count' => 'integer',
            'annual_passengers' => 'integer',
            'peak_annual_passengers' => 'integer',
            'number_of_airlines' => 'integer',
            'number_of_destinations' => 'integer',
            'number_of_destinations_asean' => 'integer',
            'number_of_destinations_outside_asean' => 'integer',
            'opened_year' => 'integer',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function nearestAirport(): BelongsTo
    {
        return $this->belongsTo(self::class, 'nearest_airport_id');
    }

    public function nearbyAirports(): HasMany
    {
        return $this->hasMany(self::class, 'nearest_airport_id');
    }

    public function airlines(): BelongsToMany
    {
        return $this->belongsToMany(Airline::class, 'airline_airport');
    }

    public function hubAirlines(): BelongsToMany
    {
        return $this->belongsToMany(Airline::class, 'airline_hub');
    }

    public function departureRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'origin_airport_id');
    }

    public function arrivalRoutes(): HasMany
    {
        return $this->hasMany(Route::class, 'destination_airport_id');
    }

    public function destinationAirports(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'routes',
            'origin_airport_id',
            'destination_airport_id'
        )->withPivot('airline_id')->withTimestamps();
    }

    public function originAirports(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'routes',
            'destination_airport_id',
            'origin_airport_id'
        )->withPivot('airline_id')->withTimestamps();
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->whereLike('label', $like)
                ->orWhereLike('iata_code', $like)
                ->orWhereLike('icao_code', $like)
                ->orWhereLike('local_name', $like)
                ->orWhereLike('operator', $like)
                ->orWhereHas('country', fn (Builder $relation) => $relation->whereLike('label', $like))
                ->orWhereHas('region', fn (Builder $relation) => $relation->whereLike('label', $like));
        });
    }

    public function scopeInAsean(Builder $query, bool $inAsean = true): Builder
    {
        return $query->where('in_asean', $inAsean);
    }

    public function scopeWithCoordinates(Builder $query): Builder
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }
}
