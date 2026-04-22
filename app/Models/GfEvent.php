<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GfEvent extends Model
{
    protected $fillable = [
        'external_id', 'league_id', 'home_team_id', 'away_team_id',
        'status_code', 'round', 'referee', 'start_at',
        'score', 'bookmaker_odds', 'synced_at',
    ];

    protected $casts = [
        'score'         => 'array',
        'bookmaker_odds'=> 'array',
        'start_at'      => 'datetime',
        'synced_at'     => 'datetime',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(GfLeague::class, 'league_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(GfTeam::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(GfTeam::class, 'away_team_id');
    }

    public function prediction(): HasOne
    {
        return $this->hasOne(GfPrediction::class, 'event_id');
    }
}