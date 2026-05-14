<?php

namespace App\Models;

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
            'cadence_label' => $offer->repaymentCadenceLabel(),
            'term_days' => (int) $offer->term_days,
            'split_installments' => $offer->splitInstallmentCount(),
        ];
    }
}
