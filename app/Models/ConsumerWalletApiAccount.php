<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

/**
 * Sanctum token holder for the consumer mobile wallet API (maps 1:1 to WhatsappWallet).
 */
class ConsumerWalletApiAccount extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasApiTokens;

    protected $fillable = [
        'whatsapp_wallet_id',
        'phone_e164',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    /**
     * Token-only accounts have no password; satisfy the contract for middleware (e.g. throttle) safely.
     */
    public function getAuthPassword(): string
    {
        return '';
    }
}
