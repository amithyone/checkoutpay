<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_order_id',
        'ticket_type_id',
        'ticket_number',
        'qr_code',
        'qr_code_data',
        'verification_token',
        'status',
        'checked_in_at',
        'checked_in_by',
    ];

    protected $casts = [
        'qr_code_data' => 'array',
        'checked_in_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const STATUS_VALID = 'valid';
    const STATUS_USED = 'used';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->ticket_number)) {
                $order = TicketOrder::find($ticket->ticket_order_id);
                $orderNumber = $order ? $order->order_number : 'TKT-' . now()->format('Ymd');
                $ticketCount = Ticket::where('ticket_order_id', $ticket->ticket_order_id)->count() + 1;
                $ticket->ticket_number = $orderNumber . '-' . str_pad($ticketCount, 3, '0', STR_PAD_LEFT);
            }

            if (empty($ticket->verification_token)) {
                $ticket->verification_token = Str::random(32);
            }
        });
    }

    /**
     * Get the ticket order
     */
    public function ticketOrder()
    {
        return $this->belongsTo(TicketOrder::class);
    }

    /**
     * Get the ticket type
     */
    public function ticketType()
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * Get the admin who checked in this ticket
     */
    public function checkedInByAdmin()
    {
        return $this->belongsTo(Admin::class, 'checked_in_by');
    }

    /**
     * Get all check-ins for this ticket
     */
    public function checkIns()
    {
        return $this->hasMany(TicketCheckIn::class);
    }

    /**
     * Check if ticket is valid
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    /**
     * Check if ticket is used
     */
    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    /**
     * Check if ticket can be checked in
     */
    public function canBeCheckedIn(): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->ticketOrder->event->isCancelled()) {
            return false;
        }

        return true;
    }

    /**
     * Get the event through ticket order
     */
    public function event()
    {
        return $this->hasOneThrough(
            Event::class,
            TicketOrder::class,
            'id', // Foreign key on ticket_orders table
            'id', // Foreign key on events table
            'ticket_order_id', // Local key on tickets table
            'event_id' // Local key on ticket_orders table
        );
    }

    /**
     * Scope for valid tickets
     */
    public function scopeValid($query)
    {
        return $query->where('status', self::STATUS_VALID);
    }

    /**
     * Scope for used tickets
     */
    public function scopeUsed($query)
    {
        return $query->where('status', self::STATUS_USED);
    }
}
