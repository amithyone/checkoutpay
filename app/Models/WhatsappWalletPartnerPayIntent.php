<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappWalletPartnerPayIntent extends Model
{
    public const STATUS_PENDING_PIN = 'pending_pin';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'business_id',
        'confirm_token',
        'phone_e164',
        'amount',
        'order_reference',
        'order_summary',
        'payer_name',
        'webhook_url',
        'client_idempotency_key',
        'status',
        'payment_id',
        'failure_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING_PIN
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
