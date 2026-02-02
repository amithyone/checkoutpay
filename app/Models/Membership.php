<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Membership extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'category_id',
        'name',
        'slug',
        'description',
        'who_is_it_for',
        'who_is_it_for_suggestions',
        'price',
        'currency',
        'duration_type',
        'duration_value',
        'features',
        'images',
        'card_logo',
        'card_graphics',
        'terms_and_conditions',
        'is_active',
        'is_featured',
        'max_members',
        'current_members',
        'city',
        'is_global',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_value' => 'integer',
        'max_members' => 'integer',
        'current_members' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_global' => 'boolean',
        'features' => 'array',
        'images' => 'array',
        'who_is_it_for_suggestions' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($membership) {
            if (empty($membership->slug)) {
                $membership->slug = Str::slug($membership->name) . '-' . Str::random(6);
            }
        });
    }

    /**
     * Get the business that owns this membership
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the category
     */
    public function category()
    {
        return $this->belongsTo(MembershipCategory::class, 'category_id');
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        $value = $this->duration_value;
        $type = $this->duration_type;
        
        $typeLabels = [
            'days' => $value == 1 ? 'Day' : 'Days',
            'weeks' => $value == 1 ? 'Week' : 'Weeks',
            'months' => $value == 1 ? 'Month' : 'Months',
            'years' => $value == 1 ? 'Year' : 'Years',
        ];

        return "{$value} " . ($typeLabels[$type] ?? $type);
    }

    /**
     * Check if membership is available (has slots)
     */
    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->max_members === null) {
            return true; // Unlimited
        }

        return $this->current_members < $this->max_members;
    }

    /**
     * Get subscriptions for this membership
     */
    public function subscriptions()
    {
        return $this->hasMany(MembershipSubscription::class);
    }

    /**
     * Get active subscriptions
     */
    public function activeSubscriptions()
    {
        return $this->hasMany(MembershipSubscription::class)->where('status', 'active')->where('expires_at', '>=', now());
    }
}
