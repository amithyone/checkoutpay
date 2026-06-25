<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessAccountApplication extends Model
{
    public const PLAN_PAYMENTS_ONLY = 'payments_only';

    public const PLAN_PAYMENTS_AND_WEB = 'payments_and_web';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_AWAITING_PASSWORD = 'awaiting_password';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    public const SERVICE_CATEGORIES = [
        'payments',
        'rentals',
        'memberships',
        'tickets',
        'charity',
        'invoices',
    ];

    protected $fillable = [
        'public_id',
        'whatsapp_wallet_id',
        'reference',
        'account_plan',
        'service_categories',
        'business_name',
        'email',
        'phone',
        'address',
        'website_url',
        'cac_document_path',
        'status',
        'progress_percent',
        'status_label',
        'fee_amount',
        'fee_currency',
        'fee_transaction_id',
        'linked_business_id',
        'rejected_reason',
        'submitted_at',
        'approved_at',
        'password_set_at',
        'meta',
    ];

    protected $casts = [
        'service_categories' => 'array',
        'fee_amount' => 'decimal:2',
        'progress_percent' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'password_set_at' => 'datetime',
        'meta' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function linkedBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'linked_business_id');
    }

    public function feeTransaction(): BelongsTo
    {
        return $this->belongsTo(WhatsappWalletTransaction::class, 'fee_transaction_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_REJECTED], true);
    }

    public function isBlockingNewApplication(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_AWAITING_PASSWORD,
        ], true);
    }

    /**
     * @return array<string, int>
     */
    public static function defaultProgressForStatus(string $status): int
    {
        return match ($status) {
            self::STATUS_SUBMITTED => 20,
            self::STATUS_UNDER_REVIEW => 50,
            self::STATUS_APPROVED => 75,
            self::STATUS_AWAITING_PASSWORD => 90,
            self::STATUS_ACTIVE => 100,
            self::STATUS_REJECTED => 0,
            default => 0,
        };
    }

    public static function countPending(): int
    {
        return self::query()
            ->whereNotIn('status', [self::STATUS_ACTIVE, self::STATUS_REJECTED])
            ->count();
    }

    public function statusDisplayLabel(): string
    {
        if (trim((string) ($this->status_label ?? '')) !== '') {
            return (string) $this->status_label;
        }

        return match ($this->status) {
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_UNDER_REVIEW => 'Under review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_AWAITING_PASSWORD => 'Set up password',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_ACTIVE, self::STATUS_REJECTED]);
    }
}
