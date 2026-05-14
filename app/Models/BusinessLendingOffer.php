<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BusinessLendingOffer extends Model
{
    public const STATUS_PENDING_ADMIN = 'pending_admin';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REJECTED = 'rejected';

    public const REPAYMENT_LUMP = 'lump';

    public const REPAYMENT_SPLIT = 'split';

    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCIES = [
        self::FREQUENCY_DAILY,
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
    ];

    protected $fillable = [
        'lender_business_id',
        'amount',
        'interest_rate_percent',
        'term_days',
        'repayment_type',
        'repayment_frequency',
        'status',
        'public_slug',
        'list_publicly',
        'starts_at',
        'ends_at',
        'admin_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'interest_rate_percent' => 'decimal:4',
        'list_publicly' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BusinessLendingOffer $offer) {
            if (empty($offer->public_slug)) {
                $offer->public_slug = Str::lower(Str::random(12));
            }
        });
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'lender_business_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(BusinessLoan::class);
    }

    public function scopePubliclyListed($query)
    {
        return $query->where('list_publicly', true)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * Number of schedule slices for this offer (must stay aligned with {@see \App\Services\Credit\BusinessPeerLoanService::buildScheduleDates}).
     */
    public function splitInstallmentCount(): int
    {
        if ($this->repayment_type === self::REPAYMENT_LUMP) {
            return 1;
        }

        $frequency = $this->repayment_frequency ?: self::FREQUENCY_WEEKLY;
        $stepDays = match ($frequency) {
            self::FREQUENCY_DAILY => 1,
            self::FREQUENCY_MONTHLY => 30,
            default => 7,
        };

        return max(1, (int) ceil($this->term_days / $stepDays));
    }

    public function repaymentCadenceLabel(): string
    {
        if ($this->repayment_type === self::REPAYMENT_LUMP) {
            return 'Lump sum at end of term';
        }

        $frequency = $this->repayment_frequency ?: self::FREQUENCY_WEEKLY;

        return match ($frequency) {
            self::FREQUENCY_DAILY => 'Daily split collections',
            self::FREQUENCY_MONTHLY => 'Monthly split collections (30-day steps)',
            default => 'Weekly split collections',
        };
    }
}
