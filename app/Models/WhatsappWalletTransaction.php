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
        if (! empty($meta['payout_failed']) || ($meta['payout_bucket'] ?? '') === MavonPayTransferService::BUCKET_FAILED) {
            return MavonPayTransferService::BUCKET_FAILED;
        }
        if (! empty($meta['payout_pending']) || ($meta['payout_bucket'] ?? '') === MavonPayTransferService::BUCKET_PENDING) {
            return MavonPayTransferService::BUCKET_PENDING;
        }
        if (($meta['payout_bucket'] ?? '') === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            return MavonPayTransferService::BUCKET_SUCCESSFUL;
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
                ->orWhere('meta->payout_failed', true);
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
                ->orWhere('meta->payout_pending', true);
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePayoutSuccessful(Builder $query): Builder
    {
        return $query
            ->where('meta->payout_bucket', MavonPayTransferService::BUCKET_SUCCESSFUL)
            ->where(function (Builder $q): void {
                $q->whereNull('meta->payout_failed')
                    ->orWhere('meta->payout_failed', false);
            });
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
