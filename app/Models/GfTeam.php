<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GfTeam extends Model
{
    protected $fillable = ['external_id', 'name'];

    public function homeEvents(): HasMany
    {
        return $this->hasMany(GfEvent::class, 'home_team_id');
    }

    public function awayEvents(): HasMany
    {
        return $this->hasMany(GfEvent::class, 'away_team_id');
    }
}