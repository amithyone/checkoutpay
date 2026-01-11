<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_id',
        'amount',
        'payer_name',
        'bank',
        'webhook_url',
        'account_number',
        'payer_account_number',
        'business_id',
        'status',
        'email_data',
        'matched_at',
        'expires_at',
        'is_mismatch',
        'received_amount',
        'mismatch_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'email_data' => 'array',
        'matched_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_mismatch' => 'boolean',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get pending payments
     */
    public static function pending()
    {
        return static::where('status', self::STATUS_PENDING)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Check if payment is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Scope for expired payments
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
            ->where('status', self::STATUS_PENDING);
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payment is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if payment is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Approve payment
     */
    public function approve(array $emailData = [], bool $isMismatch = false, ?float $receivedAmount = null, ?string $mismatchReason = null): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'email_data' => $emailData,
            'matched_at' => now(),
            'is_mismatch' => $isMismatch,
            'received_amount' => $receivedAmount,
            'mismatch_reason' => $mismatchReason,
        ]);
    }

    /**
     * Reject payment
     */
    public function reject(string $reason = ''): bool
    {
        return $this->update([
            'status' => self::STATUS_REJECTED,
            'email_data' => array_merge($this->email_data ?? [], ['rejection_reason' => $reason]),
            'matched_at' => now(),
        ]);
    }

    /**
     * Get the business that owns this payment
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get account number details
     */
    public function accountNumberDetails()
    {
        return $this->belongsTo(AccountNumber::class, 'account_number', 'account_number');
    }
}
