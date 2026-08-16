<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WithdrawalRequest extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PROCESSED = 'processed';

    public const SOURCE_DASHBOARD = 'dashboard';

    public const SOURCE_PAYOUT_API = 'payout_api';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_AUTO = 'auto';

    public const SOURCE_RENTALS_API = 'rentals_api';

    protected $fillable = [
        'business_id',
        'amount',
        'account_number',
        'account_name',
        'bank_name',
        'notes',
        'bank_narration',
        'status',
        'source',
        'payout_provider',
        'payout_status',
        'payout_reference',
        'payout_response_code',
        'payout_response_message',
        'payout_raw_response',
        'payout_attempted_at',
        'payout_failed_at',
        'payout_succeeded_at',
        'rejection_reason',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'payout_raw_response' => 'array',
        'payout_attempted_at' => 'datetime',
        'payout_failed_at' => 'datetime',
        'payout_succeeded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the business that made this withdrawal request
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the admin who processed this request
     */
    public function processor()
    {
        return $this->belongsTo(Admin::class, 'processed_by');
    }

    public function merchantPayoutMessage(): string
    {
        return app(\App\Services\Payout\MerchantPayoutMessageSanitizer::class)->forWithdrawal($this);
    }

    /**
     * Check if request is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Approve withdrawal request
     */
    public function approve($adminId = null): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'processed_by' => $adminId,
            'processed_at' => now(),
        ]);
    }

    /**
     * Reject withdrawal request
     */
    public function reject(string $reason, $adminId = null): bool
    {
        return $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'processed_by' => $adminId,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark as processed
     */
    public function markAsProcessed($adminId = null): bool
    {
        return $this->update([
            'status' => self::STATUS_PROCESSED,
            'processed_by' => $adminId,
            'processed_at' => now(),
        ]);
    }

    public function isFromPayoutApi(): bool
    {
        return $this->source === self::SOURCE_PAYOUT_API;
    }

    public function sourceLabel(): ?string
    {
        return match ($this->source) {
            self::SOURCE_PAYOUT_API => 'Payout API',
            self::SOURCE_DASHBOARD => 'Dashboard',
            self::SOURCE_ADMIN => 'Admin',
            self::SOURCE_AUTO => 'Auto-withdraw',
            self::SOURCE_RENTALS_API => 'Rentals API',
            default => null,
        };
    }
}
