<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use App\Services\MavonPayTransferService;
use App\Services\MevonPay\MevonPayPayoutMetaNormalizer;
use App\Services\Payout\BankPayoutNarration;
use App\Services\Whatsapp\WhatsappBankTransferReceiptDetails;
use App\Services\Whatsapp\WhatsappCrossBorderP2pFxService;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use App\Services\Whatsapp\WhatsappWalletPendingP2pService;
use App\Services\Whatsapp\WhatsappWalletSelfBankTransferService;
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
        private WhatsappWalletSelfBankTransferService $selfBankTransfer,
        private ConsumerBusinessWalletLedgerService $businessLedger,
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

                if (! $sender->hasPin()) {
                    throw new \RuntimeException('PIN not set.');
                }
                $debitCheck = $sender->canDebit($debitAmount);
                if (! $debitCheck['ok']) {
                    throw new \RuntimeException($debitCheck['message'] ?? 'Debit limit exceeded.');
                }
                $creditCheck = $recv->canCredit($creditAmount);
                if (! $creditCheck['ok']) {
                    throw new \RuntimeException($creditCheck['message'] ?? 'Recipient credit limit exceeded.');
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

            $msg = $e->getMessage();
            if (in_array($msg, ['wallet_missing', 'pair_missing', 'recipient_missing', 'PIN not set.']) || str_starts_with($msg, 'Tier 1') || $msg === 'Insufficient balance.') {
                return ['ok' => false, 'message' => $msg === 'wallet_missing' || $msg === 'pair_missing' || $msg === 'recipient_missing' ? 'Wallet not found.' : $msg];
            }

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
        ?string $remark = null,
        string $ledgerScope = ConsumerWalletTransactionScope::SCOPE_PERSONAL,
    ): array {
        $ledgerScope = ConsumerWalletTransactionScope::normalize($ledgerScope);
        if ($ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS && ! $wallet->fresh()->hasBusinessWallet()) {
            return ['ok' => false, 'message' => 'Business wallet is not linked yet.'];
        }
        if (! $this->walletCountry->isNigeriaPayInWallet((string) $wallet->phone_e164)) {
            return ['ok' => false, 'message' => 'Bank transfers are only available for Nigeria wallet numbers.'];
        }

        $acct = preg_replace('/\D/', '', $accountNumber10) ?? '';
        if (strlen($acct) !== 10 || $amount < 1 || $bankCode === '' || trim($beneficiaryName) === '') {
            return ['ok' => false, 'message' => 'Invalid transfer details.'];
        }

        $beneficiaryForMatch = trim($beneficiaryName);
        $fromEnquiry = false;
        if ($this->bankPayout->isNameEnquiryAvailable()) {
            $ne = $this->bankPayout->nameEnquiry($bankCode, $acct);
            if ($ne && ! $this->bankPayout->isWeakVerifiedName($ne['account_name'] ?? null)) {
                $beneficiaryForMatch = trim((string) $ne['account_name']);
                $fromEnquiry = true;
            }
        }

        $isSelf = $this->selfBankTransfer->isSelfTransfer($wallet, $acct, $bankCode, $beneficiaryForMatch, $fromEnquiry);
        $quoted = $this->selfBankTransfer->quote($amount, $isSelf);
        if (! ($quoted['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($quoted['message'] ?? 'Invalid transfer amount.')];
        }

        $payoutAmount = (float) ($quoted['payout_amount'] ?? $amount);
        $selfFee = (float) ($quoted['fee'] ?? 0);
        $narration = BankPayoutNarration::forConsumerApp($remark);

        if (! $this->bankPayout->isConfigured()) {
            return $this->ledgerOnlyBankTransfer($wallet, $amount, $acct, $bankName, $bankCode, $beneficiaryName, $isSelf, $selfFee, $payoutAmount, $ledgerScope);
        }

        $reference = $this->bankPayout->makeWalletPayoutReference();

        try {
            DB::transaction(function () use ($wallet, $amount, $payoutAmount, $acct, $bankName, $bankCode, $beneficiaryName, $reference, $isSelf, $selfFee, $narration, $ledgerScope) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }
                if ($ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
                    $debit = $this->businessLedger->debitLockedWallet($w, $amount);
                    if (! $debit['ok']) {
                        throw new \RuntimeException($debit['message'] ?? 'cannot_debit');
                    }
                    $newBal = (float) $debit['balance_after'];
                } else {
                    $w->resetDailyTransferIfNeeded();
                    $check = $w->canDebit($amount);
                    if (! $check['ok']) {
                        throw new \RuntimeException($check['message'] ?? 'cannot_debit');
                    }
                    $newBal = round((float) $w->balance - $amount, 2);
                    $w->balance = $newBal;
                    $w->daily_transfer_total = round((float) $w->daily_transfer_total + $amount, 2);
                    $w->daily_transfer_for_date = now()->toDateString();
                }
                if (! $w->hasPin()) {
                    throw new \RuntimeException('PIN not set.');
                }
                $w->pin_failed_attempts = 0;
                $w->save();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
                    'ledger_scope' => $ledgerScope,
                    'amount' => $amount,
                    'balance_after' => $newBal,
                    'counterparty_account_number' => $acct,
                    'counterparty_bank_code' => $bankCode,
                    'counterparty_account_name' => $beneficiaryName,
                    'external_reference' => $reference,
                    'meta' => array_filter([
                        'bank_name' => $bankName,
                        'channel' => 'consumer_api',
                        'narration' => $narration,
                        'payout_pending' => true,
                        'self_transfer' => $isSelf ? true : null,
                        'self_transfer_fee' => $isSelf ? $selfFee : null,
                        'payout_amount' => $isSelf ? $payoutAmount : null,
                    ], static fn ($v) => $v !== null),
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('consumer_wallet.bank_debit_failed', ['error' => $e->getMessage(), 'wallet_id' => $wallet->id]);

            $msg = $e->getMessage();
            if (in_array($msg, ['wallet_missing', 'PIN not set.']) || str_starts_with($msg, 'Tier 1') || $msg === 'Insufficient balance.') {
                return ['ok' => false, 'message' => $msg === 'wallet_missing' ? 'Wallet not found.' : $msg];
            }

            $limitMsg = $wallet->isTier1() ? ' and limits' : '';
            return ['ok' => false, 'message' => "Could not complete transfer. Check balance{$limitMsg}."];
        }

        $walletFresh = $wallet->fresh();
        $txnRow = WhatsappWalletTransaction::query()
            ->where('external_reference', $reference)
            ->where('whatsapp_wallet_id', $wallet->id)
            ->first();

        $result = $this->bankPayout->sendTransfer(
            $payoutAmount,
            $bankCode,
            $bankName,
            $acct,
            $beneficiaryName,
            $reference,
            $narration,
            $walletFresh,
            $txnRow?->id,
        );
        $bucket = $result['bucket'] ?? MavonPayTransferService::BUCKET_FAILED;

        DB::transaction(function () use ($wallet, $amount, $reference, $bucket, $result, $ledgerScope) {
            $txn = WhatsappWalletTransaction::query()
                ->where('external_reference', $reference)
                ->where('whatsapp_wallet_id', $wallet->id)
                ->first();
            if (! $txn) {
                Log::error('consumer_wallet.payout_txn_missing', ['reference' => $reference, 'wallet_id' => $wallet->id]);
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if ($w) {
                    if ($ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
                        $this->businessLedger->creditLockedWallet($w, $amount);
                    } else {
                        $w->balance = round((float) $w->balance + $amount, 2);
                        $w->daily_transfer_total = max(0, round((float) $w->daily_transfer_total - $amount, 2));
                    }
                    $w->save();
                }

                return;
            }

            $meta = MevonPayPayoutMetaNormalizer::mergeIntoMeta(
                array_merge(is_array($txn->meta) ? $txn->meta : [], [
                    'payout_bucket' => $bucket,
                ]),
                $result,
            );

            $refund = $bucket === MavonPayTransferService::BUCKET_FAILED;
            $txnLedgerScope = ConsumerWalletTransactionScope::normalize((string) ($txn->ledger_scope ?? ConsumerWalletTransactionScope::SCOPE_PERSONAL));

            if ($refund) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if ($w) {
                    if ($txnLedgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
                        $this->businessLedger->creditLockedWallet($w, $amount);
                    } else {
                        $w->balance = round((float) $w->balance + $amount, 2);
                        $w->daily_transfer_total = max(0, round((float) $w->daily_transfer_total - $amount, 2));
                    }
                    $w->save();
                }
                $meta['reversed_at'] = now()->toIso8601String();
                $meta['payout_pending'] = false;
                $meta['payout_failed'] = true;
            } elseif ($bucket === MavonPayTransferService::BUCKET_PENDING) {
                $meta['payout_pending'] = true;
                $meta['whatsapp_payout_processing'] = true;
            } else {
                $meta['payout_pending'] = false;
                $meta['payout_reference'] = $result['reference'] ?? $reference;
            }

            $txn->update(['meta' => $meta]);
        });

        if ($bucket === MavonPayTransferService::BUCKET_FAILED) {
            $walletFresh = $wallet->fresh();
            if ($ledgerScope !== ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
                $this->walletNotifier->notifyMoneyReceived(
                    $walletFresh,
                    $amount,
                    (float) $walletFresh->balance,
                    null,
                    ['credit_source' => 'payout_refund'],
                );
            }
        }

        $wallet = $wallet->fresh();
        $receipt = WhatsappBankTransferReceiptDetails::fromPayoutResult($result, null);
        if ($receipt['reference'] === '') {
            $receipt['reference'] = (string) ($result['reference'] ?? $reference);
        }
        $balanceAfter = $ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS
            ? $this->businessLedger->resolvedBalance($wallet)
            : (float) $wallet->balance;

        if ($bucket === MavonPayTransferService::BUCKET_SUCCESSFUL || $bucket === MavonPayTransferService::BUCKET_PENDING) {
            return [
                'ok' => true,
                'message' => $bucket === MavonPayTransferService::BUCKET_PENDING
                    ? 'Bank transfer is processing. Your wallet has been debited.'
                    : 'Bank transfer sent.',
                'data' => [
                    'reference' => $receipt['reference'],
                    'session_id' => $receipt['session_id'] !== '' ? $receipt['session_id'] : null,
                    'response_message' => $receipt['response_message'] !== '' ? $receipt['response_message'] : null,
                    'balance_after' => $balanceAfter,
                    'ledger_scope' => $ledgerScope,
                    'amount_debited' => $amount,
                    'payout_amount' => $payoutAmount,
                    'self_transfer' => $isSelf,
                    'self_transfer_fee' => $selfFee,
                    'bucket' => $bucket,
                ],
            ];
        }

        return [
            'ok' => false,
            'message' => (string) ($result['response_message'] ?? 'Bank transfer not completed. Wallet refunded if applicable.'),
            'data' => [
                'balance_after' => $balanceAfter,
                'ledger_scope' => $ledgerScope,
                'bucket' => $bucket,
                'session_id' => $receipt['session_id'] !== '' ? $receipt['session_id'] : null,
                'response_message' => $receipt['response_message'] !== '' ? $receipt['response_message'] : null,
                'reference' => $receipt['reference'] !== '' ? $receipt['reference'] : null,
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
        bool $isSelf = false,
        float $selfFee = 0.0,
        float $payoutAmount = 0.0,
        string $ledgerScope = ConsumerWalletTransactionScope::SCOPE_PERSONAL,
    ): array {
        $ledgerScope = ConsumerWalletTransactionScope::normalize($ledgerScope);
        try {
            DB::transaction(function () use ($wallet, $amount, $acct, $bankName, $bankCode, $beneficiary, $isSelf, $selfFee, $payoutAmount, $ledgerScope) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }
                if ($ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
                    $debit = $this->businessLedger->debitLockedWallet($w, $amount);
                    if (! $debit['ok']) {
                        throw new \RuntimeException($debit['message'] ?? 'cannot_debit');
                    }
                    $newBal = (float) $debit['balance_after'];
                } else {
                    $w->resetDailyTransferIfNeeded();
                    $check = $w->canDebit($amount);
                    if (! $check['ok']) {
                        throw new \RuntimeException($check['message'] ?? 'cannot_debit');
                    }
                    $newBal = round((float) $w->balance - $amount, 2);
                    $w->balance = $newBal;
                    $w->daily_transfer_total = round((float) $w->daily_transfer_total + $amount, 2);
                    $w->daily_transfer_for_date = now()->toDateString();
                }
                if (! $w->hasPin()) {
                    throw new \RuntimeException('PIN not set.');
                }
                $w->pin_failed_attempts = 0;
                $w->save();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
                    'ledger_scope' => $ledgerScope,
                    'amount' => $amount,
                    'balance_after' => $newBal,
                    'counterparty_account_number' => $acct,
                    'counterparty_bank_code' => $bankCode,
                    'counterparty_account_name' => $beneficiary,
                    'meta' => array_filter([
                        'bank_name' => $bankName,
                        'channel' => 'consumer_api',
                        'payout_mode' => 'ledger_only',
                        'self_transfer' => $isSelf ? true : null,
                        'self_transfer_fee' => $isSelf ? $selfFee : null,
                        'payout_amount' => $isSelf ? $payoutAmount : null,
                    ], static fn ($v) => $v !== null),
                ]);
            });
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (in_array($msg, ['wallet_missing', 'PIN not set.']) || str_starts_with($msg, 'Tier 1') || $msg === 'Insufficient balance.') {
                return ['ok' => false, 'message' => $msg === 'wallet_missing' ? 'Wallet not found.' : $msg];
            }
            return ['ok' => false, 'message' => 'Could not record transfer.'];
        }

        $walletFresh = $wallet->fresh();

        return [
            'ok' => true,
            'message' => 'Transfer recorded (ledger-only until live payouts are enabled).',
            'data' => [
                'balance_after' => (float) ($ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS
                    ? $this->businessLedger->resolvedBalance($walletFresh)
                    : $walletFresh->balance),
                'ledger_scope' => $ledgerScope,
                'amount_debited' => $amount,
                'payout_amount' => $isSelf ? $payoutAmount : $amount,
                'self_transfer' => $isSelf,
                'self_transfer_fee' => $selfFee,
            ],
        ];
    }
}
