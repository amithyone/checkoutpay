<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappWalletTransaction extends Model
{
    public const TYPE_TOPUP = 'topup';

    public const TYPE_BANK_TRANSFER_OUT = 'bank_transfer_out';

    public const TYPE_P2P_DEBIT = 'p2p_debit';

    public const TYPE_P2P_CREDIT = 'p2p_credit';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'whatsapp_wallet_id',
        'sender_name',
        'type',
        'amount',
        'balance_after',
        'counterparty_phone_e164',
        'counterparty_account_number',
        'counterparty_bank_code',
        'counterparty_account_name',
        'external_reference',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }
}
