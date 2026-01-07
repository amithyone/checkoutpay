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

    protected $fillable = [
        'business_id',
        'amount',
        'account_number',
        'account_name',
        'bank_name',
        'status',
        'rejection_reason',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
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
}
