<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Airline extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'external_id',
        'label',
        'airline_code',
        'hub_airport_id',
        'website',
        'dbpedia_url',
        'wikidata_url',
    ];

    public function hubAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'hub_airport_id');
    }

    public function hubs(): BelongsToMany
    {
        return $this->belongsToMany(Airport::class, 'airline_hub');
    }

    public function airports(): BelongsToMany
    {
        return $this->belongsToMany(Airport::class, 'airline_airport');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
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
                ->orWhereLike('airline_code', $like);
        });
    }
}
