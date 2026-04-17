<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bets',
        'stake',
        'total_odds',
        'potential_winning',
        'status',
        'ticket_number',
        'notes',
    ];

    protected $casts = [
        'bets' => 'array',
        'stake' => 'decimal:2',
        'total_odds' => 'decimal:4',
        'potential_winning' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the ticket.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate unique ticket number
     */
    public static function generateTicketNumber()
    {
        do {
            $number = 'TKT-' . strtoupper(uniqid());
        } while (self::where('ticket_number', $number)->exists());

        return $number;
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for date range filter
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
