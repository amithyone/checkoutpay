<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'code',
        'discount_type',
        'discount_value',
        'usage_limit',
        'used_count',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the event that owns this coupon
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Check if coupon is valid
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        $now = now();
        if ($this->valid_from && $now < $this->valid_from) {
            return false;
        }

        if ($this->valid_until && $now > $this->valid_until) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount
     */
    public function calculateDiscount(float $amount): float
    {
        if ($this->discount_type === 'percentage') {
            return ($amount * $this->discount_value) / 100;
        } else {
            return min($this->discount_value, $amount); // Fixed amount, can't exceed original price
        }
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
