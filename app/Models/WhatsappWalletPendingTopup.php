<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappWalletPendingTopup extends Model
{
    protected $fillable = [
        'whatsapp_wallet_id',
        'payment_id',
        'account_number',
        'account_name',
        'bank_name',
        'bank_code',
        'expires_at',
        'fulfilled_at',
        'amount_reported',
        'amount_credited',
        'mavon_reference',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'amount_reported' => 'decimal:2',
        'amount_credited' => 'decimal:2',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
