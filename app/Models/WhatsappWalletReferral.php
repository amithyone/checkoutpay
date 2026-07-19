<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappWalletReferral extends Model
{
    public const SOURCE_CODE = 'code';

    public const SOURCE_PHONE = 'phone';

    public const SOURCE_FIRST_P2P = 'first_p2p';

    protected $fillable = [
        'referred_wallet_id',
        'referrer_wallet_id',
        'attribution_source',
        'referral_code_used',
        'attributed_at',
        'bonus_ends_at',
        'counted_tx_total',
        'milestones_paid',
        'first_deposit_bonus_paid_at',
    ];

    protected $casts = [
        'attributed_at' => 'datetime',
        'bonus_ends_at' => 'datetime',
        'first_deposit_bonus_paid_at' => 'datetime',
        'counted_tx_total' => 'integer',
        'milestones_paid' => 'integer',
    ];

    public function referredWallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'referred_wallet_id');
    }

    public function referrerWallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'referrer_wallet_id');
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(WhatsappWalletReferralBonus::class, 'referral_id');
    }

    public function isBonusWindowOpen(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? \Carbon\Carbon::instance($at) : now();

        return $this->bonus_ends_at !== null && $at->lte($this->bonus_ends_at);
    }
}
