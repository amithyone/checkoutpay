<?php

namespace App\Services\Consumer;

use App\Models\WalletSavingsLock;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletReferral;
use App\Models\WhatsappWalletReferralBonus;
use App\Models\WhatsappWalletTransaction;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WalletReferralBonusService
{
    public function __construct(
        private WalletReferralSettingsService $settings,
        private ConsumerWalletPushNotificationService $push,
        private WhatsappWalletCountryResolver $walletCountry,
    ) {}

    public function onTopup(WhatsappWallet $wallet, WhatsappWalletTransaction $topupTxn): void
    {
        if (! $this->settings->enabled()) {
            return;
        }
        if ($topupTxn->type !== WhatsappWalletTransaction::TYPE_TOPUP) {
            return;
        }

        $referral = WhatsappWalletReferral::query()
            ->where('referred_wallet_id', $wallet->id)
            ->first();
        if (! $referral || ! $referral->isBonusWindowOpen()) {
            return;
        }
        if ($referral->first_deposit_bonus_paid_at !== null) {
            return;
        }

        $priorTopups = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('type', WhatsappWalletTransaction::TYPE_TOPUP)
            ->where('id', '<', $topupTxn->id)
            ->exists();
        if ($priorTopups) {
            return;
        }

        $amount = (float) $topupTxn->amount;
        $min = $this->settings->firstDepositMinNgn();
        if ($amount < $min) {
            return;
        }

        $percent = $this->settings->firstDepositPercent();
        if ($percent <= 0) {
            return;
        }

        $bonus = round($amount * ($percent / 100), 2);
        $max = $this->settings->firstDepositMaxNgn();
        if ($max !== null) {
            $bonus = min($bonus, $max);
        }
        if ($bonus <= 0) {
            return;
        }

        $this->payBonus(
            $referral,
            WhatsappWalletReferralBonus::TYPE_FIRST_DEPOSIT,
            $bonus,
            'NGN',
            'first_deposit:'.$topupTxn->id,
            WhatsappWalletTransaction::TYPE_REFERRAL_BONUS_FIRST_DEPOSIT,
            [
                'source_topup_transaction_id' => $topupTxn->id,
                'topup_amount' => $amount,
                'snapshot' => $this->settings->snapshot(),
            ],
            function (WhatsappWalletReferral $locked) {
                $locked->first_deposit_bonus_paid_at = now();
                $locked->save();
            },
        );
    }

    public function onCountedTransaction(WhatsappWallet $wallet, WhatsappWalletTransaction $txn): void
    {
        if (! $this->settings->enabled()) {
            return;
        }
        if (! in_array($txn->type, WhatsappWalletTransaction::REFERRAL_COUNTED_TYPES, true)) {
            return;
        }

        $referral = WhatsappWalletReferral::query()
            ->where('referred_wallet_id', $wallet->id)
            ->first();
        if (! $referral) {
            return;
        }

        DB::transaction(function () use ($referral, $txn) {
            $locked = WhatsappWalletReferral::query()
                ->where('id', $referral->id)
                ->lockForUpdate()
                ->first();
            if (! $locked) {
                return;
            }

            $locked->counted_tx_total = (int) $locked->counted_tx_total + 1;
            $locked->save();

            if (! $locked->isBonusWindowOpen()) {
                return;
            }

            $every = $this->settings->milestoneEvery();
            $amount = $this->settings->milestoneAmountNgn();
            if ($every < 1 || $amount <= 0) {
                return;
            }

            $referrer = WhatsappWallet::query()->find($locked->referrer_wallet_id);
            if (! $referrer || $referrer->status !== WhatsappWallet::STATUS_ACTIVE) {
                return;
            }
            if (! $this->walletCountry->isNigeriaPayInWallet((string) $referrer->phone_e164)) {
                return;
            }

            while ((int) $locked->counted_tx_total >= ((int) $locked->milestones_paid + 1) * $every) {
                $nextMilestone = (int) $locked->milestones_paid + 1;
                $paid = $this->payBonus(
                    $locked,
                    WhatsappWalletReferralBonus::TYPE_TX_MILESTONE,
                    $amount,
                    'NGN',
                    'milestone:'.$locked->id.':'.$nextMilestone,
                    WhatsappWalletTransaction::TYPE_REFERRAL_BONUS_MILESTONE,
                    [
                        'milestone' => $nextMilestone,
                        'counted_tx_total' => (int) $locked->counted_tx_total,
                        'source_transaction_id' => $txn->id,
                        'snapshot' => $this->settings->snapshot(),
                    ],
                    function (WhatsappWalletReferral $r) use ($nextMilestone) {
                        $r->milestones_paid = $nextMilestone;
                        $r->save();
                    },
                );
                if (! $paid) {
                    break;
                }
                $locked->refresh();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  callable(WhatsappWalletReferral): void|null  $afterPay
     */
    public function payBonus(
        WhatsappWalletReferral $referral,
        string $bonusType,
        float $amount,
        string $currency,
        string $idempotencyKey,
        string $txnType,
        array $meta = [],
        ?callable $afterPay = null,
    ): bool {
        if ($amount <= 0) {
            return false;
        }

        if (WhatsappWalletReferralBonus::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return true;
        }

        try {
            return DB::transaction(function () use ($referral, $bonusType, $amount, $currency, $idempotencyKey, $txnType, $meta, $afterPay) {
                if (WhatsappWalletReferralBonus::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->exists()) {
                    return true;
                }

                $referrer = WhatsappWallet::query()
                    ->where('id', $referral->referrer_wallet_id)
                    ->lockForUpdate()
                    ->first();
                if (! $referrer || $referrer->status !== WhatsappWallet::STATUS_ACTIVE) {
                    return false;
                }
                if ((int) $referrer->id === (int) $referral->referred_wallet_id) {
                    return false;
                }

                // Referral rewards go to flexible savings (not spendable wallet balance).
                $spendableBal = round((float) $referrer->balance, 2);
                $newFlexible = round((float) ($referrer->flexible_savings_balance ?? 0) + $amount, 2);
                $referrer->flexible_savings_balance = $newFlexible;
                $referrer->save();

                $walletTxn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $referrer->id,
                    'type' => $txnType,
                    'ledger_scope' => ConsumerWalletTransactionScope::SCOPE_PERSONAL,
                    'amount' => $amount,
                    'balance_after' => $spendableBal,
                    'meta' => [
                        'referral_id' => $referral->id,
                        'referred_wallet_id' => $referral->referred_wallet_id,
                        'bonus_type' => $bonusType,
                        'credited_to' => 'flexible_savings',
                        'flexible_savings_balance_after' => $newFlexible,
                    ],
                ]);

                $lock = WalletSavingsLock::query()->create([
                    'whatsapp_wallet_id' => $referrer->id,
                    'source_transaction_id' => $walletTxn->id,
                    'source' => WalletSavingsLock::SOURCE_REFERRAL_BONUS,
                    'lock_type' => WalletSavingsLock::LOCK_TYPE_FLEXIBLE,
                    'ledger_scope' => ConsumerWalletTransactionScope::SCOPE_PERSONAL,
                    'amount' => $amount,
                    'interest_rate_percent' => 0,
                    'locked_at' => now(),
                    'matures_at' => null,
                    'status' => WalletSavingsLock::STATUS_ACTIVE,
                    'meta' => [
                        'referral_id' => $referral->id,
                        'bonus_type' => $bonusType,
                        'wallet_transaction_id' => $walletTxn->id,
                    ],
                ]);

                $bonusMeta = array_merge($meta, [
                    'credited_to' => 'flexible_savings',
                    'wallet_savings_lock_id' => $lock->id,
                    'flexible_savings_balance_after' => $newFlexible,
                ]);

                $bonus = WhatsappWalletReferralBonus::query()->create([
                    'referral_id' => $referral->id,
                    'referrer_wallet_id' => $referrer->id,
                    'referred_wallet_id' => $referral->referred_wallet_id,
                    'type' => $bonusType,
                    'amount' => $amount,
                    'currency' => $currency,
                    'idempotency_key' => $idempotencyKey,
                    'meta' => $bonusMeta,
                    'wallet_transaction_id' => $walletTxn->id,
                ]);

                if ($afterPay) {
                    $lockedRef = WhatsappWalletReferral::query()
                        ->where('id', $referral->id)
                        ->lockForUpdate()
                        ->first();
                    if ($lockedRef) {
                        $afterPay($lockedRef);
                    }
                }

                $pushAmount = $amount;
                $pushType = $bonusType;
                DB::afterCommit(function () use ($referrer, $pushAmount, $pushType, $newFlexible) {
                    try {
                        $label = match ($pushType) {
                            WhatsappWalletReferralBonus::TYPE_FIRST_DEPOSIT => 'Referral bonus: first deposit',
                            WhatsappWalletReferralBonus::TYPE_TX_MILESTONE => 'Referral bonus: activity milestone',
                            WhatsappWalletReferralBonus::TYPE_LEADERBOARD => 'Referral leaderboard prize',
                            default => 'Referral bonus',
                        };
                        $this->push->notifyGeneric(
                            $referrer->fresh(),
                            $label,
                            '₦'.number_format($pushAmount, 2).' was added to your flexible savings from referrals.',
                            [
                                'type' => 'referral_bonus',
                                'bonus_type' => $pushType,
                                'amount' => (string) $pushAmount,
                                'credited_to' => 'flexible_savings',
                                'flexible_savings_balance_after' => (string) $newFlexible,
                                'screen' => 'saving',
                            ],
                        );
                    } catch (\Throwable $e) {
                        Log::debug('wallet.referral.push_failed', ['error' => $e->getMessage()]);
                    }
                });

                Log::info('wallet.referral.bonus_paid', [
                    'bonus_id' => $bonus->id,
                    'type' => $bonusType,
                    'amount' => $amount,
                    'referrer_wallet_id' => $referrer->id,
                    'credited_to' => 'flexible_savings',
                    'lock_id' => $lock->id,
                ]);

                return true;
            });
        } catch (\Throwable $e) {
            Log::warning('wallet.referral.bonus_failed', [
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);

            return WhatsappWalletReferralBonus::query()->where('idempotency_key', $idempotencyKey)->exists();
        }
    }
}
