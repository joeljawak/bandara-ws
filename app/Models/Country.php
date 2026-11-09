<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'external_id',
        'label',
        'member_of_asean',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'member_of_asean' => 'boolean',
        ];
    }

    public function airports(): HasMany
    {
        return $this->hasMany(Airport::class);
    }

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }
}
