<?php

namespace App\Models;

use App\Models\Concerns\UsesFusekiConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirportAlias extends Model
{
    use UsesFusekiConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'airport_id',
        'alias',
    ];

    public function airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class);
    }
}
