<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'title',
        'description',
        'venue',
        'address',
        'start_date',
        'end_date',
        'timezone',
        'cover_image',
        'max_attendees',
        'max_tickets_per_customer',
        'allow_refunds',
        'refund_policy',
        'commission_percentage',
        'status',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'allow_refunds' => 'boolean',
        'commission_percentage' => 'decimal:2',
        'max_attendees' => 'integer',
        'max_tickets_per_customer' => 'integer',
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the business that owns this event
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get ticket types for this event
     */
    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class);
    }

    /**
     * Get ticket orders for this event
     */
    public function ticketOrders()
    {
        return $this->hasMany(TicketOrder::class);
    }

    /**
     * Get tickets for this event
     */
    public function tickets()
    {
        return $this->hasManyThrough(Ticket::class, TicketType::class);
    }

    /**
     * Check if event is published
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Get cover image URL
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        if (!$this->cover_image) {
            return null;
        }
        return \Illuminate\Support\Facades\Storage::url($this->cover_image);
    }
}
