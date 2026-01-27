<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'title',
        'slug',
        'description',
        'venue',
        'address',
        'start_date',
        'end_date',
        'timezone',
        'cover_image',
        'status',
        'max_attendees',
        'max_tickets_per_customer',
        'allow_refunds',
        'refund_policy',
        'ticket_template',
        'ticket_design_settings',
        'commission_percentage',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'max_attendees' => 'integer',
        'max_tickets_per_customer' => 'integer',
        'allow_refunds' => 'boolean',
        'ticket_design_settings' => 'array',
        'commission_percentage' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->title) . '-' . Str::random(6);
            }
        });
    }

    /**
     * Get the business that owns the event
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get all ticket types for this event
     */
    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class);
    }

    /**
     * Get all ticket orders for this event
     */
    public function ticketOrders()
    {
        return $this->hasMany(TicketOrder::class);
    }

    /**
     * Get all tickets for this event
     */
    public function tickets()
    {
        return $this->hasManyThrough(Ticket::class, TicketOrder::class);
    }

    /**
     * Check if event is published
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Check if event is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if event is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if event has started
     */
    public function hasStarted(): bool
    {
        return $this->start_date <= now();
    }

    /**
     * Check if event has ended
     */
    public function hasEnded(): bool
    {
        return $this->end_date <= now();
    }

    /**
     * Get total tickets sold
     */
    public function getTotalTicketsSoldAttribute(): int
    {
        return $this->ticketOrders()
            ->where('payment_status', 'paid')
            ->where('status', 'confirmed')
            ->withCount('tickets')
            ->get()
            ->sum('tickets_count');
    }

    /**
     * Get total revenue
     */
    public function getTotalRevenueAttribute(): float
    {
        return $this->ticketOrders()
            ->where('payment_status', 'paid')
            ->where('status', 'confirmed')
            ->sum('total_amount');
    }

    /**
     * Get total commission
     */
    public function getTotalCommissionAttribute(): float
    {
        return $this->ticketOrders()
            ->where('payment_status', 'paid')
            ->where('status', 'confirmed')
            ->sum('commission_amount');
    }

    /**
     * Check if tickets are still available
     */
    public function hasAvailableTickets(): bool
    {
        if ($this->max_attendees === null) {
            return true; // No limit
        }

        return $this->total_tickets_sold < $this->max_attendees;
    }

    /**
     * Scope for published events
     */
    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Scope for active events (published and not ended)
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED)
            ->where('end_date', '>', now());
    }
}
