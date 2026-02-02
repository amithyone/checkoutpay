<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MembershipSubscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'membership_id',
        'payment_id',
        'member_name',
        'member_email',
        'member_phone',
        'subscription_number',
        'start_date',
        'expires_at',
        'status',
        'qr_code_data',
        'card_pdf_path',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expires_at' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscription) {
            if (empty($subscription->subscription_number)) {
                $subscription->subscription_number = 'SUB-' . strtoupper(Str::random(8));
            }
        });
    }

    /**
     * Get the membership
     */
    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    /**
     * Get the payment
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at >= now();
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Get days remaining
     */
    public function getDaysRemainingAttribute(): int
    {
        if ($this->isExpired()) {
            return 0;
        }
        return max(0, now()->diffInDays($this->expires_at));
    }
}
