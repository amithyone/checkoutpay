<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventCheckIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'event_id',
        'checked_in_by',
        'check_in_method',
        'check_in_time',
        'notes',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
