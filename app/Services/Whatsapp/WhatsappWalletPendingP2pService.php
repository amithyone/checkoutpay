<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletPendingP2pCredit;
use App\Models\WhatsappWalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * P2P to a number without a wallet yet: sender debited immediately; recipient claims by opening WALLET; else refund after TTL.
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
     * @return array{ok: true, expires_at: \Carbon\Carbon}|array{ok: false, message: string}
     */
    public function createHold(
        WhatsappWallet $senderWallet,
        string $recipientPhoneE164,
        float $amount,
        string $evolutionInstance,
    ): array {
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Invalid amount.'];
        }

        $expiresAt = now()->addMinutes(self::claimMinutes());

        try {
            $result = DB::transaction(function () use ($senderWallet, $recipientPhoneE164, $amount, $expiresAt) {
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
                    'amount' => $amount,
                    'status' => WhatsappWalletPendingP2pCredit::STATUS_PENDING,
                    'expires_at' => $expiresAt,
                    'meta' => ['channel' => 'whatsapp_menu'],
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
                        'claim_deadline' => $expiresAt->toIso8601String(),
                    ],
                ]);

                $pending->sender_debit_transaction_id = $debitTxn->id;
                $pending->save();

                return ['expires_at' => $expiresAt, 'sender' => $sender->fresh()];
            });

            $senderFresh = $result['sender'];
            $expiresAtOut = $result['expires_at'];

            DB::afterCommit(function () use (
                $evolutionInstance,
                $recipientPhoneE164,
                $amount,
                $senderFresh,
                $expiresAtOut
            ) {
                $this->notifyRecipientToClaim(
                    $evolutionInstance,
                    $recipientPhoneE164,
                    $amount,
                    (string) $senderFresh->phone_e164,
                    $senderFresh->normalizedSenderName(),
                    $expiresAtOut
                );
            });

            return ['ok' => true, 'expires_at' => $expiresAtOut];
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

    public static function claimMinutes(): int
    {
        return max(5, min(120, (int) config('whatsapp.wallet.p2p_pending_claim_minutes', 30)));
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
            ->where('expires_at', '>', now())
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
                if ($pending->expires_at->isPast()) {
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
                DB::afterCommit(function () use ($recv, $amount, $senderId, $evolutionInstance) {
                    $sender = WhatsappWallet::query()->find($senderId);
                    if ($sender) {
                        $this->walletNotifier->notifyP2pReceived(
                            $evolutionInstance,
                            $recv->fresh(),
                            $amount,
                            (string) $sender->phone_e164,
                            $sender->normalizedSenderName(),
                            now()
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
     * Refund all pending rows past expiry. Returns count refunded.
     */
    public function expireAndRefundDue(): int
    {
        $ids = WhatsappWalletPendingP2pCredit::query()
            ->where('status', WhatsappWalletPendingP2pCredit::STATUS_PENDING)
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
     * Recipient sent *CANCEL* / *DECLINE*: refund all unexpired pending incoming for this number.
     */
    public function refundIncomingPendingAsRecipient(string $recipientPhoneE164, string $evolutionInstance): int
    {
        $ids = WhatsappWalletPendingP2pCredit::query()
            ->where('recipient_phone_e164', $recipientPhoneE164)
            ->where('status', WhatsappWalletPendingP2pCredit::STATUS_PENDING)
            ->where('expires_at', '>', now())
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
                ? 'The transfer was *cancelled*. The sender has been *refunded*.'
                : "*{$n}* waiting transfers were *cancelled*. The senders have been *refunded*.";
            $this->client->sendText(
                $evolutionInstance,
                $recipientPhoneE164,
                "*Declined*\n\n".$line
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

                $amount = (float) $pending->amount;
                $newBal = round((float) $sender->balance + $amount, 2);
                $sender->balance = $newBal;
                $sender->save();

                $metaReason = $reason === 'recipient_declined'
                    ? 'recipient_declined_cancel'
                    : 'recipient_did_not_open_wallet_in_time';

                $refundTxn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $sender->id,
                    'sender_name' => $sender->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_ADJUSTMENT,
                    'amount' => $amount,
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
                $when = now()->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A').
                    ' ('.(string) config('app.timezone').')';

                $senderBody = $reason === 'recipient_declined'
                    ? "*P2P refund*\n\n".
                        'The recipient *declined* the transfer (they sent *CANCEL*).'."\n\n".
                        "*To:* {$mask}\n".
                        '*Amount:* ₦'.number_format($amount, 2)."\n".
                        "*Time:* {$when}\n\n".
                        'Your wallet was *credited back*. New balance: *₦'.number_format($newBal, 2).'*'
                    : "*P2P refund*\n\n".
                        'We could not deliver your WhatsApp send — the recipient did not open *WALLET* here in time.'."\n\n".
                        "*To:* {$mask}\n".
                        '*Amount:* ₦'.number_format($amount, 2)."\n".
                        "*Time:* {$when}\n\n".
                        'Your wallet was *credited back*. New balance: *₦'.number_format($newBal, 2).'*';

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
        Carbon $expiresAt
    ): void {
        if ($evolutionInstance === '') {
            $evolutionInstance = (string) config('whatsapp.evolution.instance', '');
        }
        if ($evolutionInstance === '') {
            return;
        }

        $amountStr = '₦'.number_format($amount, 2);
        $deadline = $expiresAt->copy()->timezone(config('app.timezone'))->format('M j, g:i A').
            ' ('.(string) config('app.timezone').')';
        $fromWho = trim((string) $senderDisplayName);
        $senderLabel = $fromWho !== '' ? $fromWho : 'A wallet user';
        $maskedSender = $this->maskPhoneTail($senderPhoneE164);
        $mins = self::claimMinutes();
        $brand = (string) config('whatsapp.bot_brand_name', 'CheckoutNow');

        $this->client->sendText(
            $evolutionInstance,
            $recipientPhoneE164,
            "💸 *{$amountStr}* from *{$senderLabel}*\n".
            "*Number:* {$maskedSender}\n\n".
            "No {$brand} wallet on this chat yet.\n".
            "Send *WALLET* → *REGISTER* (PIN) → *your name*\n\n".
            "*CANCEL* — money returns to sender\n\n".
            "*Claim by {$deadline}* ({$mins} min)"
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
