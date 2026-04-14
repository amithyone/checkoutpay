<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletPendingP2pCredit;
use App\Models\WhatsappWalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * P2P to a number without a wallet yet: sender debited immediately; recipient receives by opening WALLET (no time limit).
 * Recipient can *CANCEL* to refund the sender. Legacy rows with expires_at set may still be auto-refunded by the scheduler.
 */
class WhatsappWalletPendingP2pService
{
    public function __construct(
        private EvolutionWhatsAppClient $client,
        private WhatsappWalletTopupNotifier $walletNotifier,
    ) {}

    /**
     * Debit sender and record a pending credit for a recipient who has no wallet yet.
     *
     * @param  float|null  $recipientCreditAmount  When null, same as sender debit (legacy / same-currency).
     * @return array{ok: true, expires_at: null}|array{ok: false, message: string}
     */
    public function createHold(
        WhatsappWallet $senderWallet,
        string $recipientPhoneE164,
        float $amount,
        string $evolutionInstance,
        ?float $recipientCreditAmount = null,
    ): array {
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Invalid amount.'];
        }

        $credit = $recipientCreditAmount !== null ? round((float) $recipientCreditAmount, 2) : round($amount, 2);
        if ($credit < 0.01) {
            return ['ok' => false, 'message' => 'Invalid recipient amount after conversion.'];
        }

        try {
            $result = DB::transaction(function () use ($senderWallet, $recipientPhoneE164, $amount, $credit) {
                $ids = array_values(array_unique(array_filter([$senderWallet->id])));
                sort($ids, SORT_NUMERIC);
                $locked = [];
                foreach ($ids as $id) {
                    $w = WhatsappWallet::query()->lockForUpdate()->find($id);
                    if (! $w) {
                        throw new \RuntimeException('wallet_missing');
                    }
                    $locked[$id] = $w;
                }
                $sender = $locked[$senderWallet->id] ?? null;
                if (! $sender) {
                    throw new \RuntimeException('sender_missing');
                }

                $sender->resetDailyTransferIfNeeded();
                if (! $sender->hasPin() || ! $sender->canDebit($amount)['ok']) {
                    throw new \RuntimeException('limits');
                }

                $newSenderBal = round((float) $sender->balance - $amount, 2);
                $sender->balance = $newSenderBal;
                $sender->daily_transfer_total = round((float) $sender->daily_transfer_total + $amount, 2);
                $sender->daily_transfer_for_date = now()->toDateString();
                $sender->pin_failed_attempts = 0;
                $sender->save();

                $pending = WhatsappWalletPendingP2pCredit::query()->create([
                    'sender_wallet_id' => $sender->id,
                    'recipient_phone_e164' => $recipientPhoneE164,
                    'amount' => $credit,
                    'status' => WhatsappWalletPendingP2pCredit::STATUS_PENDING,
                    'expires_at' => null,
                    'meta' => [
                        'channel' => 'whatsapp_menu',
                        'sender_debit_amount' => round($amount, 2),
                        'recipient_credit_amount' => $credit,
                    ],
                ]);

                $debitTxn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $sender->id,
                    'sender_name' => $sender->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_P2P_DEBIT,
                    'amount' => $amount,
                    'balance_after' => $newSenderBal,
                    'counterparty_phone_e164' => $recipientPhoneE164,
                    'meta' => [
                        'channel' => 'whatsapp_menu',
                        'awaiting_recipient_wallet' => true,
                        'pending_p2p_id' => $pending->id,
                        'recipient_credit_amount' => $credit,
                    ],
                ]);

                $pending->sender_debit_transaction_id = $debitTxn->id;
                $pending->save();

                return ['sender' => $sender->fresh()];
            });

            $senderFresh = $result['sender'];

            DB::afterCommit(function () use (
                $evolutionInstance,
                $recipientPhoneE164,
                $credit,
                $senderFresh,
                $amount,
            ) {
                $resolver = app(WhatsappWalletCountryResolver::class);
                $creditCur = $resolver->currencyForPhoneE164($recipientPhoneE164);
                $senderCur = $resolver->currencyForPhoneE164((string) $senderFresh->phone_e164);
                $this->notifyRecipientToClaim(
                    $evolutionInstance,
                    $recipientPhoneE164,
                    $credit,
                    (string) $senderFresh->phone_e164,
                    $senderFresh->normalizedSenderName(),
                    $creditCur,
                    round((float) $amount, 2),
                    $senderCur,
                );
            });

            return ['ok' => true, 'expires_at' => null];
        } catch (\RuntimeException $e) {
            $code = $e->getMessage();
            Log::warning('whatsapp.wallet.p2p_pending_hold_failed', ['error' => $code, 'wallet_id' => $senderWallet->id]);

            return match ($code) {
                'limits' => ['ok' => false, 'message' => 'Cannot send that amount (balance or daily limit).'],
                default => ['ok' => false, 'message' => 'Send failed. Try again.'],
            };
        } catch (\Throwable $e) {
            Log::error('whatsapp.wallet.p2p_pending_hold_error', ['message' => $e->getMessage(), 'wallet_id' => $senderWallet->id]);

            return ['ok' => false, 'message' => 'Send failed. Try again.'];
        }
    }

    /**
     * Claim any pending credits for this wallet's phone (call after wallet exists / on submenu).
     */
    public function tryClaimForWallet(WhatsappWallet $wallet, string $evolutionInstance): void
    {
        $phone = (string) $wallet->phone_e164;
        if ($phone === '' || ! $wallet->isActive()) {
            return;
        }

        $ids = WhatsappWalletPendingP2pCredit::query()
            ->where('recipient_phone_e164', $phone)
            ->where('status', WhatsappWalletPendingP2pCredit::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach ($ids as $id) {
            $this->claimOne((int) $id, $evolutionInstance);
        }
    }

    private function claimOne(int $pendingId, string $evolutionInstance): void
    {
        try {
            DB::transaction(function () use ($pendingId, $evolutionInstance) {
                $pending = WhatsappWalletPendingP2pCredit::query()
                    ->lockForUpdate()
                    ->find($pendingId);
                if (! $pending || $pending->status !== WhatsappWalletPendingP2pCredit::STATUS_PENDING) {
                    return;
                }
                if ($pending->expires_at !== null && $pending->expires_at->isPast()) {
                    return;
                }

                $recv = WhatsappWallet::query()
                    ->where('phone_e164', $pending->recipient_phone_e164)
                    ->where('status', WhatsappWallet::STATUS_ACTIVE)
                    ->lockForUpdate()
                    ->first();
                if (! $recv) {
                    return;
                }

                $amount = (float) $pending->amount;
                $recv->resetDailyTransferIfNeeded();
                if (! $recv->canCredit($amount)['ok']) {
                    Log::warning('whatsapp.wallet.p2p_claim_skipped_limits', [
                        'pending_id' => $pending->id,
                        'recipient_wallet_id' => $recv->id,
                    ]);

                    return;
                }

                $newBal = round((float) $recv->balance + $amount, 2);
                $recv->balance = $newBal;
                $recv->save();

                $sender = $pending->senderWallet;
                $senderName = $sender?->normalizedSenderName();

                $creditTxn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $recv->id,
                    'sender_name' => $senderName,
                    'type' => WhatsappWalletTransaction::TYPE_P2P_CREDIT,
                    'amount' => $amount,
                    'balance_after' => $newBal,
                    'counterparty_phone_e164' => $sender?->phone_e164 ?? '',
                    'meta' => [
                        'channel' => 'whatsapp_menu',
                        'from_pending_p2p_id' => $pending->id,
                    ],
                ]);

                $pending->status = WhatsappWalletPendingP2pCredit::STATUS_CLAIMED;
                $pending->claimed_at = now();
                $pending->meta = array_merge(is_array($pending->meta) ? $pending->meta : [], [
                    'credit_transaction_id' => $creditTxn->id,
                ]);
                $pending->save();

                $senderId = (int) $pending->sender_wallet_id;
                $pendingMeta = is_array($pending->meta) ? $pending->meta : [];
                $senderDebitFromMeta = isset($pendingMeta['sender_debit_amount']) && is_numeric($pendingMeta['sender_debit_amount'])
                    ? (float) $pendingMeta['sender_debit_amount']
                    : null;
                DB::afterCommit(function () use ($recv, $amount, $senderId, $evolutionInstance, $senderDebitFromMeta) {
                    $sender = WhatsappWallet::query()->find($senderId);
                    if ($sender) {
                        $resolver = app(WhatsappWalletCountryResolver::class);
                        $recvCur = $resolver->currencyForPhoneE164((string) $recv->phone_e164);
                        $senderCur = $resolver->currencyForPhoneE164((string) $sender->phone_e164);
                        $crossBorderFx = null;
                        if ($senderDebitFromMeta !== null && strtoupper($senderCur) !== strtoupper($recvCur)) {
                            $crossBorderFx = [
                                'debit_amount' => round($senderDebitFromMeta, 2),
                                'debit_currency' => $senderCur,
                                'credit_amount' => round($amount, 2),
                                'credit_currency' => $recvCur,
                            ];
                        }
                        $this->walletNotifier->notifyP2pReceived(
                            $evolutionInstance,
                            $recv->fresh(),
                            $amount,
                            (string) $sender->phone_e164,
                            $sender->normalizedSenderName(),
                            now(),
                            $recvCur,
                            $crossBorderFx,
                        );
                    }
                });
            });
        } catch (\Throwable $e) {
            Log::error('whatsapp.wallet.p2p_claim_failed', [
                'pending_id' => $pendingId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Refund pending rows whose expires_at is set and in the past (legacy TTL holds only).
     */
    public function expireAndRefundDue(): int
    {
        $ids = WhatsappWalletPendingP2pCredit::query()
            ->where('status', WhatsappWalletPendingP2pCredit::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $n = 0;
        foreach ($ids as $id) {
            if ($this->refundOne((int) $id, 'expired', '')) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Recipient sent *CANCEL* / *DECLINE*: refund all pending incoming for this number.
     */
    public function refundIncomingPendingAsRecipient(string $recipientPhoneE164, string $evolutionInstance): int
    {
        $ids = WhatsappWalletPendingP2pCredit::query()
            ->where('recipient_phone_e164', $recipientPhoneE164)
            ->where('status', WhatsappWalletPendingP2pCredit::STATUS_PENDING)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $n = 0;
        foreach ($ids as $id) {
            if ($this->refundOne((int) $id, 'recipient_declined', $evolutionInstance)) {
                $n++;
            }
        }

        if ($n > 0 && $evolutionInstance !== '') {
            $line = $n === 1
                ? 'Sender refunded.'
                : "*{$n}* sends refunded.";
            $this->client->sendText(
                $evolutionInstance,
                $recipientPhoneE164,
                '*Declined* · '.$line
            );
        }

        return $n;
    }

    private function refundOne(int $pendingId, string $reason = 'expired', string $evolutionInstance = ''): bool
    {
        $refunded = false;

        try {
            DB::transaction(function () use ($pendingId, &$refunded, $reason, $evolutionInstance) {
                $pending = WhatsappWalletPendingP2pCredit::query()
                    ->lockForUpdate()
                    ->find($pendingId);
                if (! $pending || $pending->status !== WhatsappWalletPendingP2pCredit::STATUS_PENDING) {
                    return;
                }

                $sender = WhatsappWallet::query()
                    ->whereKey($pending->sender_wallet_id)
                    ->lockForUpdate()
                    ->first();
                if (! $sender || ! $sender->isActive()) {
                    Log::warning('whatsapp.wallet.p2p_refund_no_sender', ['pending_id' => $pending->id]);

                    return;
                }

                $metaPend = is_array($pending->meta) ? $pending->meta : [];
                $senderRefund = isset($metaPend['sender_debit_amount']) && is_numeric($metaPend['sender_debit_amount'])
                    ? (float) $metaPend['sender_debit_amount']
                    : (float) $pending->amount;
                $newBal = round((float) $sender->balance + $senderRefund, 2);
                $sender->balance = $newBal;
                $sender->save();

                $metaReason = $reason === 'recipient_declined'
                    ? 'recipient_declined_cancel'
                    : 'recipient_did_not_open_wallet_in_time';

                $refundTxn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $sender->id,
                    'sender_name' => $sender->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_ADJUSTMENT,
                    'amount' => $senderRefund,
                    'balance_after' => $newBal,
                    'counterparty_phone_e164' => $pending->recipient_phone_e164,
                    'meta' => [
                        'p2p_pending_refund' => true,
                        'pending_p2p_id' => $pending->id,
                        'reason' => $metaReason,
                    ],
                ]);

                if ($pending->sender_debit_transaction_id) {
                    $debitTxn = WhatsappWalletTransaction::query()->find($pending->sender_debit_transaction_id);
                    if ($debitTxn) {
                        $m = is_array($debitTxn->meta) ? $debitTxn->meta : [];
                        $m['refunded_unclaimed'] = true;
                        $m['refund_transaction_id'] = $refundTxn->id;
                        $debitTxn->meta = $m;
                        $debitTxn->save();
                    }
                }

                $pending->status = WhatsappWalletPendingP2pCredit::STATUS_REFUNDED;
                $pending->refunded_at = now();
                $pending->sender_refund_transaction_id = $refundTxn->id;
                $pending->save();

                $refunded = true;

                $instance = $evolutionInstance !== ''
                    ? $evolutionInstance
                    : (string) config('whatsapp.evolution.instance', '');
                $senderPhone = (string) $sender->phone_e164;
                $mask = $this->maskPhoneTail($pending->recipient_phone_e164);

                $resolver = app(WhatsappWalletCountryResolver::class);
                $senderCur = $resolver->currencyForPhoneE164((string) $sender->phone_e164);
                $amtFmt = WhatsappWalletMoneyFormatter::format($senderRefund, $senderCur);
                $balFmt = WhatsappWalletMoneyFormatter::format($newBal, $senderCur);

                $senderBody = $reason === 'recipient_declined'
                    ? "*Refund* · they *CANCEL*\n".
                        "{$mask} · {$amtFmt}\n".
                        'Bal: *'.$balFmt.'*'
                    : "*Refund* · unclaimed\n".
                        "{$mask} · {$amtFmt}\n".
                        'Bal: *'.$balFmt.'*';

                DB::afterCommit(function () use ($instance, $senderPhone, $senderBody) {
                    if ($instance === '' || $senderPhone === '') {
                        return;
                    }
                    $this->client->sendText($instance, $senderPhone, $senderBody);
                });
            });
        } catch (\Throwable $e) {
            Log::error('whatsapp.wallet.p2p_refund_failed', [
                'pending_id' => $pendingId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        return $refunded;
    }

    public function notifyRecipientToClaim(
        string $evolutionInstance,
        string $recipientPhoneE164,
        float $amount,
        string $senderPhoneE164,
        ?string $senderDisplayName,
        string $creditCurrency = 'NGN',
        ?float $senderDebitAmount = null,
        ?string $senderDebitCurrency = null,
    ): void {
        if ($evolutionInstance === '') {
            $evolutionInstance = (string) config('whatsapp.evolution.instance', '');
        }
        if ($evolutionInstance === '') {
            return;
        }

        $creditCur = strtoupper($creditCurrency);
        $amountStr = WhatsappWalletMoneyFormatter::format($amount, $creditCur);
        $fromWho = trim((string) $senderDisplayName);
        $senderLabel = $fromWho !== '' ? $fromWho : 'Someone';
        $maskedSender = $this->maskPhoneTail($senderPhoneE164);

        $dCur = $senderDebitCurrency !== null ? strtoupper($senderDebitCurrency) : '';
        $isFx = $senderDebitAmount !== null && $senderDebitAmount > 0 && $dCur !== '' && $dCur !== $creditCur;
        if ($isFx) {
            $debitFmt = WhatsappWalletMoneyFormatter::format((float) $senderDebitAmount, $dCur);
            $rate = WhatsappWalletMoneyFormatter::crossRateLine((float) $senderDebitAmount, $dCur, $amount, $creditCur);
            $body = "🌍 *Money waiting for you* (international)\n\n".
                "*{$senderLabel}* sent *{$debitFmt}* → you'll get *{$amountStr}*\n".
                ($rate !== '' ? "*Approx. rate:* {$rate}\n" : '').
                "\n".
                "*From:* {$maskedSender}\n\n".
                "Send *WALLET* then *REGISTER* to claim · *CANCEL* refunds them";
        } else {
            $body = "💸 *{$senderLabel}* sent you *{$amountStr}*\n\n".
                "*From number:* {$maskedSender}\n\n".
                "Send *WALLET* → *REGISTER* to claim it\n".
                "*CANCEL* → refund to sender";
        }

        $this->client->sendText(
            $evolutionInstance,
            $recipientPhoneE164,
            $body
        );
    }

    private function maskPhoneTail(string $e164Digits): string
    {
        $d = preg_replace('/\D/', '', $e164Digits) ?? '';
        if (strlen($d) < 9) {
            return '••••';
        }

        return substr($d, 0, 5).' •••• '.substr($d, -4);
    }
}
