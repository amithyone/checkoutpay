<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumerDeviceStepupSession extends Model
{
    protected $fillable = [
        'session_token',
        'consumer_wallet_api_account_id',
        'phone_e164',
        'whatsapp_wallet_id',
        'auth_verified_at',
        'bvn_verified_at',
        'otp_verified_at',
        'stepup_token',
        'stepup_token_expires_at',
        'expires_at',
    ];

    protected $casts = [
        'auth_verified_at' => 'datetime',
        'bvn_verified_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'stepup_token_expires_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ConsumerWalletApiAccount::class, 'consumer_wallet_api_account_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isStepupTokenValid(string $token): bool
    {
        return hash_equals((string) $this->stepup_token, $token)
            && $this->stepup_token_expires_at !== null
            && $this->stepup_token_expires_at->isFuture()
            && ! $this->isExpired();
    }
}
