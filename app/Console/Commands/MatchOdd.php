<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchOdd extends Model
{
    protected $fillable = [
        'match_id',
        'odds_source_id',
        'odds_type',
        'odds_value',
        'bookmaker_name',
    ];

    protected $casts = [
        'odds_value' => 'decimal:2',
    ];

    /**
     * Get the match this odd belongs to
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(SportsMatch::class, 'match_id');
    }

    /**
     * Get the odds source
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(OddsSource::class, 'odds_source_id');
    }
}

