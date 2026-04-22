<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GfLeague extends Model
{
    protected $fillable = ['external_id', 'name', 'country_code', 'type', 'women'];

    protected $casts = ['women' => 'boolean'];

    public function events(): HasMany
    {
        return $this->hasMany(GfEvent::class, 'league_id');
    }
}