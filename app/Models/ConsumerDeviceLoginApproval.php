<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumerDeviceLoginApproval extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'approval_id',
        'consumer_device_stepup_session_id',
        'consumer_wallet_api_account_id',
        'status',
        'expires_at',
        'resolved_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function stepupSession(): BelongsTo
    {
        return $this->belongsTo(ConsumerDeviceStepupSession::class, 'consumer_device_stepup_session_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ConsumerWalletApiAccount::class, 'consumer_wallet_api_account_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function markExpiredIfNeeded(): void
    {
        if ($this->status === self::STATUS_PENDING && $this->isExpired()) {
            $this->status = self::STATUS_EXPIRED;
            $this->resolved_at = now();
            $this->save();
        }
    }
}
