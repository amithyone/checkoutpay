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
     * Number of schedule slices (must stay aligned with {@see \App\Services\Credit\BusinessPeerLoanService::buildScheduleDatesFromParts}).
     */
    public static function splitInstallmentCountFor(string $repaymentType, ?string $repaymentFrequency, int $termDays): int
    {
        if ($repaymentType === self::REPAYMENT_LUMP) {
            return 1;
        }

        $frequency = $repaymentFrequency ?: self::FREQUENCY_WEEKLY;
        $stepDays = match ($frequency) {
            self::FREQUENCY_DAILY => 1,
            self::FREQUENCY_MONTHLY => 30,
            default => 7,
        };

        return max(1, (int) ceil($termDays / $stepDays));
    }

    public function splitInstallmentCount(): int
    {
        return self::splitInstallmentCountFor(
            (string) $this->repayment_type,
            $this->repayment_frequency,
            (int) $this->term_days
        );
    }

    public static function repaymentCadenceLabelFor(string $repaymentType, ?string $repaymentFrequency): string
    {
        if ($repaymentType === self::REPAYMENT_LUMP) {
            return 'Lump sum at end of term';
        }

        $frequency = $repaymentFrequency ?: self::FREQUENCY_WEEKLY;

        return match ($frequency) {
            self::FREQUENCY_DAILY => 'Daily split collections',
            self::FREQUENCY_MONTHLY => 'Monthly split collections (30-day steps)',
            default => 'Weekly split collections',
        };
    }

    public function repaymentCadenceLabel(): string
    {
        return self::repaymentCadenceLabelFor((string) $this->repayment_type, $this->repayment_frequency);
    }

    /**
     * One-line explanation for marketplace / dashboards (aligned with {@see BusinessPeerLoanService::buildScheduleDatesFromParts}).
     */
    public static function formatRepaymentSummaryLine(string $repaymentType, ?string $repaymentFrequency, int $termDays): string
    {
        if ($repaymentType === self::REPAYMENT_LUMP) {
            return 'Lump sum: one full repayment on the last day of the '.$termDays.'-day term.';
        }

        $n = self::splitInstallmentCountFor($repaymentType, $repaymentFrequency, $termDays);
        $frequency = $repaymentFrequency ?: self::FREQUENCY_WEEKLY;
        $stepWord = match ($frequency) {
            self::FREQUENCY_DAILY => 'each day',
            self::FREQUENCY_MONTHLY => 'every 30 days',
            default => 'about every 7 days',
        };

        return 'Split: '.$n.' equal installment'.($n === 1 ? '' : 's').' (total repayment ÷ '.$n.') over '.$termDays.' days, collected '.$stepWord.' until the term ends.';
    }

    public function repaymentSummaryLine(): string
    {
        return self::formatRepaymentSummaryLine(
            (string) $this->repayment_type,
            $this->repayment_frequency,
            (int) $this->term_days
        );
    }
}
