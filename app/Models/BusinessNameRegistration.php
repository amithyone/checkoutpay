<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessNameRegistration extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const ID_TYPE_NIN = 'nin';

    public const ID_TYPE_PASSPORT = 'passport';

    public const ID_TYPE_DRIVERS_LICENSE = 'drivers_license';

    protected $fillable = [
        'public_id',
        'whatsapp_wallet_id',
        'reference',
        'proposed_name',
        'alternate_name',
        'owner_full_name',
        'owner_phone',
        'owner_email',
        'business_address',
        'nature_of_business',
        'id_type',
        'id_document_path',
        'status',
        'progress_percent',
        'status_label',
        'fee_amount',
        'fee_currency',
        'fee_transaction_id',
        'rejected_reason',
        'submitted_at',
        'approved_at',
        'estimated_completion_hours_min',
        'estimated_completion_hours_max',
        'approved_business_name',
        'business_account_number',
        'business_account_name',
        'business_bank_name',
        'business_bank_code',
        'meta',
    ];

    protected $casts = [
        'fee_amount' => 'decimal:2',
        'progress_percent' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'estimated_completion_hours_min' => 'integer',
        'estimated_completion_hours_max' => 'integer',
        'meta' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function feeTransaction(): BelongsTo
    {
        return $this->belongsTo(WhatsappWalletTransaction::class, 'fee_transaction_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true);
    }

    public function isPending(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * @return array<string, int>
     */
    public static function defaultProgressForStatus(string $status): int
    {
        return match ($status) {
            self::STATUS_PENDING_PAYMENT => 5,
            self::STATUS_PAID => 15,
            self::STATUS_PROCESSING => 40,
            self::STATUS_UNDER_REVIEW => 65,
            self::STATUS_APPROVED => 100,
            self::STATUS_REJECTED => 0,
            default => 0,
        };
    }

    public static function countPending(): int
    {
        return self::query()
            ->whereNotIn('status', [self::STATUS_APPROVED, self::STATUS_REJECTED])
            ->count();
    }

    public function statusDisplayLabel(): string
    {
        if (trim((string) ($this->status_label ?? '')) !== '') {
            return (string) $this->status_label;
        }

        return match ($this->status) {
            self::STATUS_PENDING_PAYMENT => 'Pending payment',
            self::STATUS_PAID => 'Paid — queued',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_UNDER_REVIEW => 'Under review',
            self::STATUS_APPROVED => 'Approved',
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
        return $query->whereNotIn('status', [self::STATUS_APPROVED, self::STATUS_REJECTED]);
    }
}
