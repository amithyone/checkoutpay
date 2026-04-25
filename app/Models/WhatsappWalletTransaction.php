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

    public const TYPE_VTU_AIRTIME = 'vtu_airtime';

    public const TYPE_VTU_DATA = 'vtu_data';

    public const TYPE_VTU_ELECTRICITY = 'vtu_electricity';

    /** Merchant X-API-Key partner API: wallet debit to pay the authenticated business. */
    public const TYPE_PARTNER_MERCHANT_PAY = 'partner_merchant_pay';

    /** @deprecated Use TYPE_PARTNER_MERCHANT_PAY; kept for existing rows. */
    public const TYPE_TAGINE_MERCHANT_PAY = 'tagine_merchant_pay';

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
