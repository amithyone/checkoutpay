<?php

namespace App\Models;

use App\Services\MavonPayTransferService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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

    public const TYPE_VTU_CABLE = 'vtu_cable';

    public const TYPE_VTU_BETTING = 'vtu_betting';

    public const TYPE_VIRTUAL_CARD_FEE = 'virtual_card_fee';

    public const TYPE_VIRTUAL_CARD_TOPUP = 'virtual_card_topup';

    public const TYPE_VIRTUAL_CARD_WITHDRAW = 'virtual_card_withdraw';

    /** Merchant X-API-Key partner API: wallet debit to pay the authenticated business. */
    public const TYPE_PARTNER_MERCHANT_PAY = 'partner_merchant_pay';

    public const TYPE_BUSINESS_NAME_REGISTRATION_FEE = 'business_name_registration_fee';

    public const TYPE_BUSINESS_ACCOUNT_ONBOARDING_FEE = 'business_account_onboarding_fee';

    /** Linked CheckoutPay merchant Rubies VA bank transfer in. */
    public const TYPE_BUSINESS_RUBIES_IN = 'business_rubies_in';

    /** Personal savings lock (funds moved to locked savings balance). */
    public const TYPE_SAVINGS_LOCK = 'savings_lock';

    /** Savings maturity payout (principal + interest back to spendable balance). */
    public const TYPE_SAVINGS_MATURITY = 'savings_maturity';

    /** Flexible savings withdrawal back to spendable balance. */
    public const TYPE_SAVINGS_WITHDRAW = 'savings_withdraw';

    /** Save Together group pot: member contribution (escrow lock). */
    public const TYPE_SAVE_TOGETHER_CONTRIBUTE = 'save_together_contribute';

    /** Save Together group pot: return member contribution after unlock. */
    public const TYPE_SAVE_TOGETHER_WITHDRAW = 'save_together_withdraw';

    /** Save Together: member invited to a group pot (informational, amount 0). */
    public const TYPE_SAVE_TOGETHER_INVITE = 'save_together_invite';

    /** Save Together: member declined an invite (informational, amount 0). */
    public const TYPE_SAVE_TOGETHER_DECLINE = 'save_together_decline';

    /** @deprecated Use TYPE_PARTNER_MERCHANT_PAY; kept for existing rows. */
    public const TYPE_TAGINE_MERCHANT_PAY = 'tagine_merchant_pay';

    public const TYPE_REFERRAL_BONUS_FIRST_DEPOSIT = 'referral_bonus_first_deposit';

    public const TYPE_REFERRAL_BONUS_MILESTONE = 'referral_bonus_milestone';

    public const TYPE_REFERRAL_BONUS_LEADERBOARD = 'referral_bonus_leaderboard';

    /** Outbound activity that counts toward referrer milestones. */
    public const REFERRAL_COUNTED_TYPES = [
        self::TYPE_VTU_AIRTIME,
        self::TYPE_VTU_DATA,
        self::TYPE_VTU_ELECTRICITY,
        self::TYPE_VTU_CABLE,
        self::TYPE_VTU_BETTING,
        self::TYPE_P2P_DEBIT,
        self::TYPE_BANK_TRANSFER_OUT,
        self::TYPE_PARTNER_MERCHANT_PAY,
        self::TYPE_TAGINE_MERCHANT_PAY,
    ];

    protected $fillable = [
        'whatsapp_wallet_id',
        'sender_name',
        'type',
        'ledger_scope',
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

    public function mevonLedgerEntries(): MorphMany
    {
        return $this->morphMany(MevonPayLedgerEntry::class, 'source');
    }

    /**
     * Payout bucket from meta (failed, pending, successful, unknown).
     */
    public function payoutBucketLabel(): string
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $bucket = (string) ($meta['payout_bucket'] ?? '');

        // Explicit bucket wins when present (avoids stale payout_failed after a later success).
        if ($bucket === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            return MavonPayTransferService::BUCKET_SUCCESSFUL;
        }
        if ($bucket === MavonPayTransferService::BUCKET_PENDING) {
            return MavonPayTransferService::BUCKET_PENDING;
        }
        if ($bucket === MavonPayTransferService::BUCKET_FAILED || ! empty($meta['payout_failed'])) {
            return MavonPayTransferService::BUCKET_FAILED;
        }
        if (! empty($meta['payout_pending'])) {
            return MavonPayTransferService::BUCKET_PENDING;
        }

        return 'unknown';
    }

    public function isReversed(): bool
    {
        $meta = is_array($this->meta) ? $this->meta : [];

        return ! empty($meta['reversed_at']);
    }

    public function canManualRefund(): bool
    {
        if ($this->type !== self::TYPE_BANK_TRANSFER_OUT || $this->isReversed()) {
            return false;
        }

        $meta = is_array($this->meta) ? $this->meta : [];

        return ! empty($meta['payout_pending'])
            || ($meta['payout_bucket'] ?? '') === MavonPayTransferService::BUCKET_PENDING;
    }

    /**
     * False auto-refund that can be clawed back after MevonPay confirms success.
     */
    public function canClawbackFalseRefund(): bool
    {
        if ($this->type !== self::TYPE_BANK_TRANSFER_OUT || ! $this->isReversed()) {
            return false;
        }

        $meta = is_array($this->meta) ? $this->meta : [];
        if (! empty($meta['clawback_at'])) {
            return false;
        }

        return ($meta['admin_refund_reason'] ?? '') === 'provider_status_failed'
            || ! empty($meta['provider_success_after_reversal'])
            || ($meta['provider_status_bucket'] ?? '') === MavonPayTransferService::BUCKET_SUCCESSFUL
            || ($meta['payout_bucket'] ?? '') === MavonPayTransferService::BUCKET_SUCCESSFUL;
    }

    /**
     * Count failed bank payouts in the last N days (sidebar badge).
     */
    public static function countFailedBankPayoutsRecent(int $days = 30): int
    {
        return static::query()
            ->bankTransferOut()
            ->where('created_at', '>=', now()->subDays($days))
            ->payoutFailed()
            ->count();
    }

    /**
     * Count pending bank payouts in the last N days (sidebar badge).
     */
    public static function countPendingBankPayoutsRecent(int $days = 30): int
    {
        return static::query()
            ->bankTransferOut()
            ->where('created_at', '>=', now()->subDays($days))
            ->payoutPending()
            ->count();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBankTransferOut(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_BANK_TRANSFER_OUT);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeP2p(Builder $query): Builder
    {
        return $query->whereIn('type', [self::TYPE_P2P_DEBIT, self::TYPE_P2P_CREDIT]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePayoutFailed(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('meta->payout_bucket', MavonPayTransferService::BUCKET_FAILED)
                ->orWhere(function (Builder $q2): void {
                    // Legacy rows may only have payout_failed; ignore when bucket is terminal success/pending.
                    $q2->where('meta->payout_failed', true)
                        ->where(function (Builder $q3): void {
                            $q3->whereNull('meta->payout_bucket')
                                ->orWhereNotIn('meta->payout_bucket', [
                                    MavonPayTransferService::BUCKET_SUCCESSFUL,
                                    MavonPayTransferService::BUCKET_PENDING,
                                ]);
                        });
                });
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePayoutPending(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('meta->payout_bucket', MavonPayTransferService::BUCKET_PENDING)
                ->orWhere(function (Builder $q2): void {
                    $q2->where('meta->payout_pending', true)
                        ->where(function (Builder $q3): void {
                            $q3->whereNull('meta->payout_bucket')
                                ->orWhereNotIn('meta->payout_bucket', [
                                    MavonPayTransferService::BUCKET_SUCCESSFUL,
                                    MavonPayTransferService::BUCKET_FAILED,
                                ]);
                        });
                });
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePayoutSuccessful(Builder $query): Builder
    {
        return $query->where('meta->payout_bucket', MavonPayTransferService::BUCKET_SUCCESSFUL);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePayoutStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            MavonPayTransferService::BUCKET_FAILED => $query->payoutFailed(),
            MavonPayTransferService::BUCKET_PENDING => $query->payoutPending(),
            MavonPayTransferService::BUCKET_SUCCESSFUL => $query->payoutSuccessful(),
            default => $query,
        };
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like, $term): void {
            $q->where('external_reference', 'like', $like)
                ->orWhere('counterparty_account_number', 'like', $like)
                ->orWhere('counterparty_account_name', 'like', $like)
                ->orWhere('counterparty_phone_e164', 'like', $like)
                ->orWhereHas('wallet', function (Builder $wq) use ($like): void {
                    $wq->where('phone_e164', 'like', $like);
                });

            if (is_numeric($term)) {
                $q->orWhere('id', (int) $term);
            }
        });
    }
}
