<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'order_id',
        'ticket_type_id',
        'event_id',
        'attendee_name',
        'attendee_email',
        'qr_code',
        'check_in_status',
        'checked_in_at',
        'checked_in_by',
        'is_transferable',
        'transferred_from_ticket_id',
        'metadata',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'is_transferable' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Generate unique ticket number
     */
    public static function generateTicketNumber(string $orderNumber, int $sequence): string
    {
        return $orderNumber . '-' . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->ticket_number)) {
                $order = TicketOrder::find($ticket->order_id);
                $sequence = static::where('order_id', $ticket->order_id)->count() + 1;
                $ticket->ticket_number = static::generateTicketNumber($order->order_number, $sequence);
            }
        });
    }

    /**
     * Relationships
     */
    public function order()
    {
        return $this->belongsTo(TicketOrder::class);
    }

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function checkIns()
    {
        return $this->hasMany(EventCheckIn::class);
    }

    /**
     * Scopes
     */
    public function scopeCheckedIn($query)
    {
        return $query->where('check_in_status', 'checked_in');
    }

    public function scopeNotCheckedIn($query)
    {
        return $query->where('check_in_status', 'not_checked_in');
    }

    /**
     * Check if ticket is checked in
     */
    public function isCheckedIn(): bool
    {
        return $this->check_in_status === 'checked_in';
    }
}
