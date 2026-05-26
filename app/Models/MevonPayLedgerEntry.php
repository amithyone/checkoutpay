<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

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

    public function isWalletFlow(): bool
    {
        return in_array($this->flow_type, [
            self::FLOW_WHATSAPP_TOPUP,
            self::FLOW_WHATSAPP_BANK_TRANSFER,
        ], true);
    }

    public function flowTypeLabel(): string
    {
        return match ($this->flow_type) {
            self::FLOW_WHATSAPP_TOPUP => 'Wallet top-up',
            self::FLOW_WHATSAPP_BANK_TRANSFER => 'Wallet bank transfer',
            self::FLOW_MERCHANT_CHECKOUT => 'Merchant checkout',
            self::FLOW_BUSINESS_RUBIES_VA => 'Business Rubies VA',
            self::FLOW_BUSINESS_WITHDRAWAL => 'Business withdrawal',
            default => str_replace('_', ' ', (string) $this->flow_type),
        };
    }

    public function resolveWalletTransaction(): ?WhatsappWalletTransaction
    {
        if ($this->source_id && $this->source_type) {
            $source = $this->relationLoaded('source') ? $this->source : $this->source()->first();
            if ($source instanceof WhatsappWalletTransaction) {
                return $source;
            }
        }

        if (! $this->isWalletFlow()) {
            return null;
        }

        $ref = trim((string) ($this->payout_reference ?? $this->external_reference ?? ''));
        if ($ref === '') {
            return null;
        }

        return WhatsappWalletTransaction::query()
            ->where('external_reference', $ref)
            ->first();
    }

    public function adminWalletTransactionUrl(): ?string
    {
        $txn = $this->resolveWalletTransaction();

        return $txn !== null
            ? route('admin.whatsapp-wallet.transactions.show', $txn)
            : null;
    }

    public function adminWalletTransactionLabel(): ?string
    {
        $txn = $this->resolveWalletTransaction();

        return $txn !== null ? 'Wallet transaction #'.$txn->id : null;
    }

    public function adminMevonAuditUrl(): string
    {
        $when = $this->occurred_at ?? $this->created_at ?? now();

        return route('admin.audits.mevonpay.index', array_filter([
            'from' => Carbon::parse($when)->subDay()->toDateString(),
            'to' => Carbon::parse($when)->addDay()->toDateString(),
            'direction' => $this->direction,
            'flow_type' => $this->flow_type,
        ]));
    }
}
