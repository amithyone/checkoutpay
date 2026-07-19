<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappWalletReferralBonus extends Model
{
    public const TYPE_FIRST_DEPOSIT = 'first_deposit';

    public const TYPE_TX_MILESTONE = 'tx_milestone';

    public const TYPE_LEADERBOARD = 'leaderboard';

    protected $fillable = [
        'referral_id',
        'referrer_wallet_id',
        'referred_wallet_id',
        'type',
        'amount',
        'currency',
        'idempotency_key',
        'meta',
        'wallet_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(WhatsappWalletReferral::class, 'referral_id');
    }

    public function referrerWallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'referrer_wallet_id');
    }

    public function referredWallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'referred_wallet_id');
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WhatsappWalletTransaction::class, 'wallet_transaction_id');
    }
}
