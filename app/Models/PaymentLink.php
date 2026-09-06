<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PaymentLink extends Model
{
    use SoftDeletes;

    public const REUSE_ONE_TIME = 'one_time';

    public const REUSE_REUSABLE = 'reusable';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'business_id',
        'title',
        'description',
        'amount',
        'currency',
        'reuse_mode',
        'status',
        'code',
        'view_count',
        'viewed_at',
        'paid_at',
        'collected_amount',
        'collected_count',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'view_count' => 'integer',
        'collected_count' => 'integer',
        'viewed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $link) {
            if (empty($link->code)) {
                $link->code = self::generateCode();
            }
            if (empty($link->status)) {
                $link->status = self::STATUS_ACTIVE;
            }
            if (empty($link->currency)) {
                $link->currency = 'NGN';
            }
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function linkPayments(): HasMany
    {
        return $this->hasMany(PaymentLinkPayment::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, PaymentLinkPayment::class, 'payment_link_id', 'id', 'id', 'payment_id');
    }

    public function getPaymentUrlAttribute(): string
    {
        return route('payment-links.pay', ['code' => $this->code]);
    }

    public function isOpenAmount(): bool
    {
        return $this->amount === null;
    }

    public function isReusable(): bool
    {
        return $this->reuse_mode === self::REUSE_REUSABLE;
    }

    public function isOneTime(): bool
    {
        return $this->reuse_mode === self::REUSE_ONE_TIME;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_PAUSED, self::STATUS_CANCELLED, self::STATUS_PAID], true);
    }

    public function canCollect(): bool
    {
        return $this->isActive();
    }

    public function formattedAmount(): string
    {
        if ($this->isOpenAmount()) {
            return 'Customer chooses';
        }

        return $this->currency.' '.number_format((float) $this->amount, 2);
    }
}
