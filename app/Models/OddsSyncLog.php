<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OddsSyncLog extends Model
{
    protected $fillable = [
        'odds_source_id',
        'total_matches_synced',
        'total_odds_synced',
        'status',
        'error_message',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    /**
     * Get the odds source
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(OddsSource::class, 'odds_source_id');
    }
}

