<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SportsMatch extends Model
{
    protected $fillable = [
        'external_id',
        'league',
        'home_team',
        'away_team',
        'commence_time',
        'status',
        'synced_at',
    ];

    protected $casts = [
        'commence_time' => 'datetime',
        'synced_at' => 'datetime',
    ];

    /**
     * Get all odds for this match
     */
    public function odds(): HasMany
    {
        return $this->hasMany(MatchOdd::class, 'match_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(AiPrediction::class, 'sports_match_id');
    }
}

