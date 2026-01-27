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
        'short_description',
        'event_image',
        'event_banner',
        'venue_name',
        'venue_address',
        'venue_city',
        'venue_state',
        'venue_country',
        'start_date',
        'end_date',
        'timezone',
        'status',
        'is_featured',
        'max_attendees',
        'current_attendees',
        'registration_deadline',
        'allow_waitlist',
        'waitlist_capacity',
        'organizer_name',
        'organizer_email',
        'organizer_phone',
        'terms_and_conditions',
        'refund_policy',
        'social_links',
        'seo_title',
        'seo_description',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'registration_deadline' => 'datetime',
        'is_featured' => 'boolean',
        'allow_waitlist' => 'boolean',
        'social_links' => 'array',
        'current_attendees' => 'integer',
        'max_attendees' => 'integer',
    ];

    /**
     * Generate slug from title
     */
    public static function generateSlug(string $title): string
    {
        $slug = Str::slug($title);
        $count = 0;
        while (static::where('slug', $slug)->exists()) {
            $count++;
            $slug = Str::slug($title) . '-' . $count;
        }
        return $slug;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->slug)) {
                $event->slug = static::generateSlug($event->title);
            }
        });
    }

    /**
     * Relationships
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function ticketTypes()
    {
        return $this->hasMany(TicketType::class)->orderBy('sort_order');
    }

    public function activeTicketTypes()
    {
        return $this->hasMany(TicketType::class)->where('is_active', true)->orderBy('sort_order');
    }

    public function orders()
    {
        return $this->hasMany(TicketOrder::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Scopes
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Check if event is sold out
     */
    public function isSoldOut(): bool
    {
        if (!$this->max_attendees) {
            return false;
        }
        return $this->current_attendees >= $this->max_attendees;
    }

    /**
     * Check if event is available for registration
     */
    public function isAvailableForRegistration(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        if ($this->isSoldOut() && !$this->allow_waitlist) {
            return false;
        }

        if ($this->registration_deadline && now() > $this->registration_deadline) {
            return false;
        }

        if ($this->start_date < now()) {
            return false;
        }

        return true;
    }
}
