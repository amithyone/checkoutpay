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
        'status',
        'email_data',
        'matched_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'email_data' => 'array',
        'matched_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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
        return static::where('status', self::STATUS_PENDING);
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
    public function approve(array $emailData = []): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'email_data' => $emailData,
            'matched_at' => now(),
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
}
