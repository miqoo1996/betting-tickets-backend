<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GfPrediction extends Model
{
    protected $fillable = [
        'event_id', 'match_result', 'total_goals', 'home_team_goals',
        'away_team_goals', 'both_teams_score', 'first_half_winner',
        'exact_score', 'recommended_bets', 'reasoning',
    ];

    protected $casts = [
        'match_result'     => 'array',
        'total_goals'      => 'array',
        'home_team_goals'  => 'array',
        'away_team_goals'  => 'array',
        'both_teams_score' => 'array',
        'first_half_winner'=> 'array',
        'exact_score'      => 'array',
        'recommended_bets' => 'array',
        'reasoning'        => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(GfEvent::class, 'event_id');
    }
}