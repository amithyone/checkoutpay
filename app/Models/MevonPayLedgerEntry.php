<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MevonPayLedgerEntry extends Model
{
    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const FLOW_WHATSAPP_TOPUP = 'whatsapp_topup';

    public const FLOW_WHATSAPP_BANK_TRANSFER = 'whatsapp_bank_transfer';

    public const FLOW_MERCHANT_CHECKOUT = 'merchant_checkout';

    public const FLOW_BUSINESS_RUBIES_VA = 'business_rubies_va';

    public const FLOW_BUSINESS_WITHDRAWAL = 'business_withdrawal';

    public const FLOW_OTHER = 'other';

    public const PAYOUT_API_CREATETRANSFER = 'createtransfer';

    public const PAYOUT_API_PAYOUT = 'payout';

    protected $fillable = [
        'direction',
        'flow_type',
        'gross_amount',
        'mevon_inbound_fee',
        'mevon_outbound_fee',
        'net_mevon_impact',
        'external_reference',
        'payout_reference',
        'account_number',
        'source_type',
        'source_id',
        'payout_api',
        'payout_bucket',
        'meta',
        'occurred_at',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'net_mevon_impact' => 'decimal:2',
        'mevon_inbound_fee' => 'integer',
        'mevon_outbound_fee' => 'integer',
        'meta' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
