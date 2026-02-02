<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'title',
        'slug',
        'description',
        'venue',
        'event_type',
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
        'view_count',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            // Generate slug from title if not provided
            if (empty($event->slug)) {
                $event->slug = static::generateUniqueSlug($event->title, $event->business_id);
            }
        });

        static::updating(function ($event) {
            // Regenerate slug if title changed and slug is empty
            if ($event->isDirty('title') && empty($event->slug)) {
                $event->slug = static::generateUniqueSlug($event->title, $event->business_id, $event->id);
            }
        });
    }

    /**
     * Generate a unique slug from title
     */
    protected static function generateUniqueSlug(string $title, int $businessId, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;

        // Check for uniqueness within the same business
        while (true) {
            $query = static::where('business_id', $businessId)
                ->where('slug', $slug);
            
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (!$query->exists()) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'allow_refunds' => 'boolean',
        'commission_percentage' => 'decimal:2',
        'max_attendees' => 'integer',
        'max_tickets_per_customer' => 'integer',
        'view_count' => 'integer',
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

    /**
     * Get coupons for this event
     */
    public function coupons()
    {
        return $this->hasMany(EventCoupon::class);
    }

    /**
     * Get active coupons for this event
     */
    public function activeCoupons()
    {
        return $this->hasMany(EventCoupon::class)->where('is_active', true);
    }

    /**
     * Get public event URL
     */
    public function getPublicUrlAttribute(): string
    {
        return route('tickets.show', $this);
    }

    /**
     * Increment view count
     */
    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    /**
     * Get total unique buyers count
     */
    public function getUniqueBuyersCountAttribute(): int
    {
        return $this->ticketOrders()
            ->where('payment_status', 'paid')
            ->distinct('customer_email')
            ->count('customer_email');
    }

    /**
     * Get total tickets sold count
     */
    public function getTicketsSoldCountAttribute(): int
    {
        return $this->tickets()
            ->whereHas('ticketOrder', function ($q) {
                $q->where('payment_status', 'paid');
            })
            ->count();
    }
}
