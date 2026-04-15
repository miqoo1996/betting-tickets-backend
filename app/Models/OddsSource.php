<?php

namespace App\Models;

use App\Console\Commands\MatchOdd;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OddsSource extends Model
{
    protected $fillable = [
        'name',
        'api_url',
        'api_key',
        'is_active',
        'last_synced_at',
        'sync_interval_minutes',
        'description',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get all match odds from this source
     */
    public function matchOdds(): HasMany
    {
        return $this->hasMany(MatchOdd::class, 'odds_source_id');
    }

    /**
     * Get all sync logs for this source
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(OddsSyncLog::class, 'odds_source_id');
    }
}

