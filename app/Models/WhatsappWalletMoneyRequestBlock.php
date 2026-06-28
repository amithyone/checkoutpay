<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappWalletMoneyRequestBlock extends Model
{
    protected $fillable = [
        'whatsapp_wallet_id',
        'blocked_phone_e164',
        'blocked_wallet_id',
        'blocked_display_name',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function blockedWallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'blocked_wallet_id');
    }
}
