<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\MavonPayTransferService;
use App\Services\Whatsapp\WhatsappCrossBorderP2pFxService;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use App\Services\Whatsapp\WhatsappWalletPendingP2pService;
use App\Services\Whatsapp\WhatsappWalletTopupNotifier;
use App\Services\WhatsappWalletBankPayoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bank and P2P transfers for the consumer mobile API (same ledger rules as WhatsApp menu).
 */
class ConsumerWalletTransferService
{
    public function __construct(
        private WhatsappWalletBankPayoutService $bankPayout,
        private WhatsappWalletPendingP2pService $pendingP2p,
        private WhatsappWalletTopupNotifier $walletNotifier,
        private WhatsappCrossBorderP2pFxService $crossBorderFx,
        private WhatsappWalletCountryResolver $walletCountry,
    ) {}

    private function evolutionInstance(): string
    {
        return WhatsappEvolutionConfigResolver::walletInstance();
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function p2p(WhatsappWallet $wallet, string $recipientPhoneInput, float $amount): array
    {
        $phone = (string) $wallet->phone_e164;
        $recipient = \App\Services\Whatsapp\PhoneNormalizer::canonicalNgE164Digits($recipientPhoneInput);
        if ($recipient === null) {
            return ['ok' => false, 'message' => 'Invalid recipient number.'];
        }
        if ($recipient === $phone) {
            return ['ok' => false, 'message' => 'Cannot send to your own number.'];
        }
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Invalid amount.'];
        }

        $instance = $this->evolutionInstance();
        if ($instance === '') {
            return ['ok' => false, 'message' => 'Transfer notifications are not configured (Evolution instance).'];
        }

        $eval = $this->crossBorderFx->evaluateP2p($instance, $recipient, $amount, $phone);
        if ($eval['status'] === 'blocked' || $eval['status'] === 'missing_rate') {
            return ['ok' => false, 'message' => (string) ($eval['message'] ?? 'This send is not available.')];
        }

        $debitAmount = (float) $eval['debit'];
        $creditAmount = (float) $eval['credit'];
        $senderCur = (string) $eval['sender_currency'];
        $recvCur = (string) $eval['recipient_currency'];
        $isFx = $senderCur !== $recvCur;

        $recvRow = WhatsappWallet::query()
            ->where('phone_e164', $recipient)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        if (! $recvRow) {
            $hold = $this->pendingP2p->createHold($wallet->fresh(), $recipient, $debitAmount, $instance, $creditAmount);
            if (! ($hold['ok'] ?? false)) {
                return ['ok' => false, 'message' => (string) ($hold['message'] ?? 'Send failed.')];
            }

            return [
                'ok' => true,
                'message' => 'Sent. Recipient will receive when they open the wallet on that number.',
                'data' => [
                    'pending_recipient' => true,
                    'balance_after' => (float) $wallet->fresh()->balance,
                ],
            ];
        }

        try {
            DB::transaction(function () use ($wallet, $recipient, $debitAmount, $creditAmount, $phone, $senderCur, $recvCur, $isFx) {
                $recvId = WhatsappWallet::query()->where('phone_e164', $recipient)->value('id');
                $ids = array_values(array_unique(array_filter([$wallet->id, $recvId])));
                if (count($ids) < 2) {
                    throw new \RuntimeException('recipient_missing');
                }
                sort($ids, SORT_NUMERIC);
                $locked = [];
                foreach ($ids as $id) {
                    $w = WhatsappWallet::query()->lockForUpdate()->find($id);
                    if (! $w) {
                        throw new \RuntimeException('wallet_missing');
                    }
                    $locked[$id] = $w;
                }

                $sender = $locked[$wallet->id] ?? null;
                $recv = null;
                foreach ($locked as $w) {
                    if ((string) $w->phone_e164 === $recipient) {
                        $recv = $w;
                        break;
                    }
                }
                if (! $sender || ! $recv) {
                    throw new \RuntimeException('pair_missing');
                }

                $sender->resetDailyTransferIfNeeded();
                $recv->resetDailyTransferIfNeeded();

                if (! $sender->hasPin() || ! $sender->canDebit($debitAmount)['ok'] || ! $recv->canCredit($creditAmount)['ok']) {
                    throw new \RuntimeException('limits');
                }

                $newSenderBal = round((float) $sender->balance - $debitAmount, 2);
                $newRecvBal = round((float) $recv->balance + $creditAmount, 2);

                $sender->balance = $newSenderBal;
                $sender->daily_transfer_total = round((float) $sender->daily_transfer_total + $debitAmount, 2);
                $sender->daily_transfer_for_date = now()->toDateString();
                $sender->pin_failed_attempts = 0;
                $sender->save();

                $recv->balance = $newRecvBal;
                $recv->save();

                $fxMeta = $isFx ? [
                    'cross_border' => true,
                    'sender_currency' => $senderCur,
                    'recipient_currency' => $recvCur,
                    'recipient_credit_amount' => $creditAmount,
                ] : [];

                $recipientDisplayName = $recv->displayName();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $sender->id,
                    'sender_name' => $sender->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_P2P_DEBIT,
                    'amount' => $debitAmount,
                    'balance_after' => $newSenderBal,
                    'counterparty_phone_e164' => $recipient,
                    'counterparty_account_name' => $recipientDisplayName,
                    'meta' => array_merge(['channel' => 'consumer_api'], $fxMeta),
                ]);

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $recv->id,
                    'sender_name' => $sender->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_P2P_CREDIT,
                    'amount' => $creditAmount,
                    'balance_after' => $newRecvBal,
                    'counterparty_phone_e164' => $phone,
                    'counterparty_account_name' => $sender->displayName(),
                    'meta' => array_merge(['channel' => 'consumer_api'], $fxMeta),
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('consumer_wallet.p2p_failed', ['error' => $e->getMessage(), 'wallet_id' => $wallet->id]);

            return ['ok' => false, 'message' => 'Send failed (limits or availability).'];
        }

        $sentAt = now();
        $recvFresh = WhatsappWallet::query()->where('phone_e164', $recipient)->first();
        if ($recvFresh) {
            $crossBorderFx = $isFx ? [
                'debit_amount' => $debitAmount,
                'debit_currency' => $senderCur,
                'credit_amount' => $creditAmount,
                'credit_currency' => $recvCur,
            ] : null;
            $this->walletNotifier->notifyP2pReceived(
                $instance,
                $recvFresh,
                $creditAmount,
                $phone,
                $wallet->normalizedSenderName(),
                $sentAt,
                $recvCur,
                $crossBorderFx,
            );
        }

        return [
            'ok' => true,
            'message' => 'Transfer completed.',
            'data' => [
                'balance_after' => (float) $wallet->fresh()->balance,
                'receipt_id' => 'P2P-'.$sentAt->timezone(config('app.timezone'))->format('Ymd-His'),
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function bankTransfer(
        WhatsappWallet $wallet,
        float $amount,
        string $accountNumber10,
        string $bankCode,
        string $bankName,
        string $beneficiaryName,
    ): array {
        if (! $this->walletCountry->isNigeriaPayInWallet((string) $wallet->phone_e164)) {
            return ['ok' => false, 'message' => 'Bank transfers are only available for Nigeria wallet numbers.'];
        }

        $acct = preg_replace('/\D/', '', $accountNumber10) ?? '';
        if (strlen($acct) !== 10 || $amount < 1 || $bankCode === '' || trim($beneficiaryName) === '') {
            return ['ok' => false, 'message' => 'Invalid transfer details.'];
        }

        if (! $this->bankPayout->isConfigured()) {
            return $this->ledgerOnlyBankTransfer($wallet, $amount, $acct, $bankName, $bankCode, $beneficiaryName);
        }

        $reference = $this->bankPayout->makeWalletPayoutReference();

        try {
            DB::transaction(function () use ($wallet, $amount, $acct, $bankName, $bankCode, $beneficiaryName, $reference) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }
                $w->resetDailyTransferIfNeeded();
                $check = $w->canDebit($amount);
                if (! $w->hasPin() || ! $check['ok']) {
                    throw new \RuntimeException('cannot_debit');
                }
                $newBal = round((float) $w->balance - $amount, 2);
                $w->balance = $newBal;
                $w->daily_transfer_total = round((float) $w->daily_transfer_total + $amount, 2);
                $w->daily_transfer_for_date = now()->toDateString();
                $w->pin_failed_attempts = 0;
                $w->save();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
                    'amount' => $amount,
                    'balance_after' => $newBal,
                    'counterparty_account_number' => $acct,
                    'counterparty_bank_code' => $bankCode,
                    'counterparty_account_name' => $beneficiaryName,
                    'external_reference' => $reference,
                    'meta' => [
                        'bank_name' => $bankName,
                        'channel' => 'consumer_api',
                        'payout_pending' => true,
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('consumer_wallet.bank_debit_failed', ['error' => $e->getMessage(), 'wallet_id' => $wallet->id]);

            return ['ok' => false, 'message' => 'Could not complete transfer. Check balance and limits.'];
        }

        $result = $this->bankPayout->sendTransfer($amount, $bankCode, $bankName, $acct, $beneficiaryName, $reference);
        $bucket = $result['bucket'] ?? MavonPayTransferService::BUCKET_FAILED;

        DB::transaction(function () use ($wallet, $amount, $reference, $bucket, $result) {
            $txn = WhatsappWalletTransaction::query()
                ->where('external_reference', $reference)
                ->where('whatsapp_wallet_id', $wallet->id)
                ->first();
            if (! $txn) {
                return;
            }

            $meta = array_merge(is_array($txn->meta) ? $txn->meta : [], [
                'payout_bucket' => $bucket,
                'payout_response_code' => $result['response_code'] ?? null,
                'payout_response_message' => $result['response_message'] ?? null,
            ]);

            $refund = $bucket === MavonPayTransferService::BUCKET_FAILED
                || $bucket === MavonPayTransferService::BUCKET_PENDING;

            if ($refund) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if ($w) {
                    $w->balance = round((float) $w->balance + $amount, 2);
                    $w->daily_transfer_total = max(0, round((float) $w->daily_transfer_total - $amount, 2));
                    $w->save();
                }
                $meta['reversed_at'] = now()->toIso8601String();
                $meta['payout_pending'] = false;
                $meta['payout_failed'] = true;
            } else {
                $meta['payout_pending'] = false;
                $meta['payout_reference'] = $result['reference'] ?? $reference;
            }

            $txn->update(['meta' => $meta]);
        });

        $wallet = $wallet->fresh();
        if ($bucket === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            return [
                'ok' => true,
                'message' => 'Bank transfer sent.',
                'data' => [
                    'reference' => (string) ($result['reference'] ?? $reference),
                    'balance_after' => (float) $wallet->balance,
                ],
            ];
        }

        return [
            'ok' => false,
            'message' => (string) ($result['response_message'] ?? 'Bank transfer not completed. Wallet refunded if applicable.'),
            'data' => [
                'balance_after' => (float) $wallet->balance,
                'bucket' => $bucket,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    private function ledgerOnlyBankTransfer(
        WhatsappWallet $wallet,
        float $amount,
        string $acct,
        string $bankName,
        string $bankCode,
        string $beneficiary,
    ): array {
        try {
            DB::transaction(function () use ($wallet, $amount, $acct, $bankName, $bankCode, $beneficiary) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }
                $w->resetDailyTransferIfNeeded();
                $check = $w->canDebit($amount);
                if (! $w->hasPin() || ! $check['ok']) {
                    throw new \RuntimeException('cannot_debit');
                }
                $newBal = round((float) $w->balance - $amount, 2);
                $w->balance = $newBal;
                $w->daily_transfer_total = round((float) $w->daily_transfer_total + $amount, 2);
                $w->daily_transfer_for_date = now()->toDateString();
                $w->pin_failed_attempts = 0;
                $w->save();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
                    'amount' => $amount,
                    'balance_after' => $newBal,
                    'counterparty_account_number' => $acct,
                    'counterparty_bank_code' => $bankCode,
                    'counterparty_account_name' => $beneficiary,
                    'meta' => [
                        'bank_name' => $bankName,
                        'channel' => 'consumer_api',
                        'payout_mode' => 'ledger_only',
                    ],
                ]);
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not record transfer.'];
        }

        return [
            'ok' => true,
            'message' => 'Transfer recorded (ledger-only until live payouts are enabled).',
            'data' => ['balance_after' => (float) $wallet->fresh()->balance],
        ];
    }
}
