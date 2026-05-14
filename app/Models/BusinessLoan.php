<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessLoan extends Model
{
    public const STATUS_PENDING_ADMIN = 'pending_admin';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REPAID = 'repaid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_DEFAULTED = 'defaulted';

    protected $fillable = [
        'business_lending_offer_id',
        'borrower_business_id',
        'principal',
        'total_repayment',
        'admin_repayment_type',
        'admin_repayment_frequency',
        'status',
        'disbursed_at',
        'repaid_at',
        'borrower_message',
    ];

    protected $casts = [
        'principal' => 'decimal:2',
        'total_repayment' => 'decimal:2',
        'disbursed_at' => 'datetime',
        'repaid_at' => 'datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(BusinessLendingOffer::class, 'business_lending_offer_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'borrower_business_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(BusinessLoanSchedule::class)->orderBy('sequence');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(BusinessLoanLedgerEntry::class);
    }

    public function hasCollectionLedgerActivity(): bool
    {
        if (isset($this->has_peer_collection_activity)) {
            return (bool) $this->has_peer_collection_activity;
        }

        return $this->ledgerEntries()
            ->where('entry_type', BusinessLoanLedgerEntry::TYPE_COLLECTION)
            ->exists();
    }

    /**
     * Super admins may set repayment mode per loan (overrides the marketplace offer) while pending
     * or active. If repayments already started, schedules are rebuilt with prior collections preserved
     * as one paid aggregate row (see {@see \App\Services\Credit\BusinessPeerLoanService::createSchedulesPreservingPriorPaid}).
     */
    public function canAdminEditRepaymentSchedule(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_ADMIN, self::STATUS_ACTIVE], true);
    }

    public function effectiveRepaymentType(): string
    {
        $this->loadMissing('offer');

        return $this->admin_repayment_type ?? (string) $this->offer->repayment_type;
    }

    /**
     * Meaningful only when {@see effectiveRepaymentType()} is split; otherwise null.
     */
    public function effectiveRepaymentFrequency(): ?string
    {
        if ($this->effectiveRepaymentType() === BusinessLendingOffer::REPAYMENT_LUMP) {
            return null;
        }
        $this->loadMissing('offer');

        return $this->admin_repayment_frequency
            ?? ($this->offer->repayment_frequency ?: BusinessLendingOffer::FREQUENCY_WEEKLY);
    }

    public function repaymentScheduleSummaryLine(): string
    {
        $this->loadMissing('offer');

        return BusinessLendingOffer::formatRepaymentSummaryLine(
            $this->effectiveRepaymentType(),
            $this->effectiveRepaymentFrequency(),
            (int) $this->offer->term_days
        );
    }

    /**
     * Used by peer-loan collection cron: effective repayment (loan admin override or offer).
     */
    public function scopeMatchingCollectCadence(Builder $query, string $cadence): void
    {
        $query->where('business_loans.status', self::STATUS_ACTIVE);
        $typeExpr = 'COALESCE(business_loans.admin_repayment_type, (SELECT o.repayment_type FROM business_lending_offers o WHERE o.id = business_loans.business_lending_offer_id))';
        $freqExpr = 'COALESCE(business_loans.admin_repayment_frequency, (SELECT o.repayment_frequency FROM business_lending_offers o WHERE o.id = business_loans.business_lending_offer_id))';

        match ($cadence) {
            'daily' => $query->where(function (Builder $w) use ($typeExpr, $freqExpr) {
                $w->whereRaw("{$typeExpr} = ?", [BusinessLendingOffer::REPAYMENT_LUMP])
                    ->orWhere(function (Builder $w2) use ($typeExpr, $freqExpr) {
                        $w2->whereRaw("{$typeExpr} = ?", [BusinessLendingOffer::REPAYMENT_SPLIT])
                            ->whereRaw("{$freqExpr} = ?", [BusinessLendingOffer::FREQUENCY_DAILY]);
                    });
            }),
            'weekly' => $query->whereRaw("{$typeExpr} = ?", [BusinessLendingOffer::REPAYMENT_SPLIT])
                ->where(function (Builder $w) use ($freqExpr) {
                    $w->whereRaw("{$freqExpr} IS NULL")
                        ->orWhereRaw("{$freqExpr} = ?", [BusinessLendingOffer::FREQUENCY_WEEKLY]);
                }),
            'monthly' => $query->whereRaw("{$typeExpr} = ?", [BusinessLendingOffer::REPAYMENT_SPLIT])
                ->whereRaw("{$freqExpr} = ?", [BusinessLendingOffer::FREQUENCY_MONTHLY]),
            default => throw new \InvalidArgumentException("Unknown cadence: {$cadence}"),
        };
    }

    public function totalScheduledAmount(): float
    {
        if ($this->relationLoaded('schedules')) {
            return (float) $this->schedules->sum(fn ($s) => (float) $s->amount_due);
        }

        return (float) $this->schedules()->sum('amount_due');
    }

    public function repaidAmount(): float
    {
        if ($this->relationLoaded('schedules')) {
            return (float) $this->schedules->sum(fn ($s) => (float) $s->amount_paid);
        }

        return (float) $this->schedules()->sum('amount_paid');
    }

    public function outstandingAmount(): float
    {
        $base = $this->totalScheduledAmount();
        if ($base <= 0) {
            $base = (float) $this->total_repayment;
        }

        return max(0.0, round($base - $this->repaidAmount(), 2));
    }

    public function progressPercent(): float
    {
        $base = $this->totalScheduledAmount();
        if ($base <= 0) {
            $base = (float) $this->total_repayment;
        }
        if ($base <= 0) {
            return 0.0;
        }

        return max(0.0, min(100.0, round(($this->repaidAmount() / $base) * 100, 2)));
    }

    public function scheduleProgress(): array
    {
        if ($this->relationLoaded('schedules')) {
            $total = $this->schedules->count();
            $paid = $this->schedules->where('status', 'paid')->count();

            return ['paid' => $paid, 'total' => $total];
        }

        $total = $this->schedules()->count();
        $paid = $this->schedules()->where('status', 'paid')->count();

        return ['paid' => $paid, 'total' => $total];
    }

    /**
     * Next schedule slice that still has balance to collect (respects partial payments).
     */
    public function nextCollectibleSchedule(): ?BusinessLoanSchedule
    {
        $list = $this->relationLoaded('schedules')
            ? $this->schedules->sortBy('sequence')->values()
            : $this->schedules()->orderBy('sequence')->get();

        foreach ($list as $schedule) {
            if ($schedule->remaining() >= 0.01) {
                return $schedule;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     amount: float,
     *     due_at: \Illuminate\Support\Carbon,
     *     sequence: int,
     *     total_schedules: int,
     *     cadence_label: string,
     *     term_days: int,
     *     split_installments: int
     * }|null
     */
    public function nextCollectionSummary(): ?array
    {
        $schedule = $this->nextCollectibleSchedule();
        if ($schedule === null) {
            return null;
        }

        $this->loadMissing('offer');
        $offer = $this->offer;
        if (! $offer) {
            return null;
        }

        $totalSchedules = $this->relationLoaded('schedules')
            ? $this->schedules->count()
            : (int) $this->schedules()->count();

        return [
            'amount' => round($schedule->remaining(), 2),
            'due_at' => $schedule->due_at,
            'sequence' => (int) $schedule->sequence,
            'total_schedules' => $totalSchedules,
            'cadence_label' => BusinessLendingOffer::repaymentCadenceLabelFor(
                $this->effectiveRepaymentType(),
                $this->effectiveRepaymentFrequency(),
            ),
            'term_days' => (int) $offer->term_days,
            'split_installments' => BusinessLendingOffer::splitInstallmentCountFor(
                $this->effectiveRepaymentType(),
                $this->effectiveRepaymentFrequency(),
                (int) $offer->term_days,
            ),
        ];
    }
}
