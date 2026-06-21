<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConsumerTrustedDevice extends Model
{
    protected $fillable = [
        'consumer_wallet_api_account_id',
        'label',
        'platform',
        'last_active_at',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(ConsumerWalletApiAccount::class, 'consumer_wallet_api_account_id');
    }

    public function passkey(): HasOne
    {
        return $this->hasOne(ConsumerPasskeyCredential::class, 'consumer_trusted_device_id');
    }

    public function hasPasskey(): bool
    {
        return $this->relationLoaded('passkey')
            ? $this->passkey !== null
            : $this->passkey()->exists();
    }
}
