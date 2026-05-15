<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPrediction extends Model
{
    protected $table = 'ai_predictions';

    protected $fillable = [
        'sports_match_id',
        'prompt',
        'response',
        'success',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'success' => 'boolean',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(SportsMatch::class, 'sports_match_id');
    }
}
