<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use App\Services\MavonPayTransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Route bank transfers to another wallet's Tier 2 VA internally (no MevonPay payout API).
 */
final class WhatsappWalletInternalVaTransferService
{
    public function __construct(
        private ConsumerBusinessWalletLedgerService $businessLedger,
    ) {}

    public static function normalizeAccountNumber(string $accountNumber): string
    {
        return preg_replace('/\D/', '', $accountNumber) ?? '';
    }

    public function resolveRecipientWallet(WhatsappWallet $sender, string $accountNumber): ?WhatsappWallet
    {
        $acct = self::normalizeAccountNumber($accountNumber);
        if ($acct === '') {
            return null;
        }

        $recipient = WhatsappWallet::query()
            ->where('mevon_virtual_account_number', $acct)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->where('tier', '>=', WhatsappWallet::TIER_RUBIES_VA)
            ->first();

        if ($recipient === null || (int) $recipient->id === (int) $sender->id) {
            return null;
        }

        return $recipient;
    }

    /**
     * @return array{
     *   ok: bool,
     *   message: string,
     *   bucket?: string,
     *   reference?: string,
     *   response_message?: string,
     *   debit_transaction_id?: int,
     *   credit_transaction_id?: int,
     *   balance_after?: float,
     *   recipient_wallet_id?: int,
     *   recv_balance_before?: float,
     *   recv_balance_after?: float,
     *   internal_va?: bool
     * }
     */
    public function execute(
        WhatsappWallet $sender,
        WhatsappWallet $recipient,
        float $amount,
        string $accountNumber,
        string $bankCode,
        string $bankName,
        string $beneficiaryName,
        string $channel,
        string $ledgerScope = ConsumerWalletTransactionScope::SCOPE_PERSONAL,
        ?string $senderDisplayName = null,
        ?string $remark = null,
        ?string $reference = null,
    ): array {
        $ledgerScope = ConsumerWalletTransactionScope::normalize($ledgerScope);
        $amount = round($amount, 2);

        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Invalid transfer amount.'];
        }

        if ((int) $recipient->id === (int) $sender->id) {
            return ['ok' => false, 'message' => 'Cannot transfer to your own account internally.'];
        }

        $reference ??= 'IVA'.now()->format('YmdHis').Str::upper(Str::random(6));
        $senderDisplayName ??= $this->businessLedger->resolveLedgerSenderName($sender, $ledgerScope);
        $acct = self::normalizeAccountNumber($accountNumber);

        $debitTransactionId = null;
        $creditTransactionId = null;
        $balanceAfter = null;
        $recvBalanceBefore = null;
        $recvBalanceAfter = null;

        try {
            DB::transaction(function () use (
                $sender,
                $recipient,
                $amount,
                $acct,
                $bankCode,
                $bankName,
                $beneficiaryName,
                $channel,
                $ledgerScope,
                $senderDisplayName,
                $remark,
                $reference,
                &$debitTransactionId,
                &$creditTransactionId,
                &$balanceAfter,
                &$recvBalanceBefore,
                &$recvBalanceAfter,
            ) {
                $ids = [(int) $sender->id, (int) $recipient->id];
                sort($ids, SORT_NUMERIC);
                $locked = [];
                foreach ($ids as $id) {
                    $w = WhatsappWallet::query()->lockForUpdate()->find($id);
                    if (! $w) {
                        throw new \RuntimeException('wallet_missing');
                    }
                    $locked[$id] = $w;
                }

                $senderLocked = $locked[(int) $sender->id] ?? null;
                $recipientLocked = $locked[(int) $recipient->id] ?? null;
                if (! $senderLocked || ! $recipientLocked) {
                    throw new \RuntimeException('wallet_missing');
                }

                $senderLocked->resetDailyTransferIfNeeded();
                $recipientLocked->resetDailyTransferIfNeeded();

                if (! $senderLocked->hasPin()) {
                    throw new \RuntimeException('PIN not set.');
                }

                if ($ledgerScope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
                    $debit = $this->businessLedger->debitLockedWallet($senderLocked, $amount);
                    if (! ($debit['ok'] ?? false)) {
                        throw new \RuntimeException($debit['message'] ?? 'cannot_debit');
                    }
                    $balanceAfter = (float) $debit['balance_after'];
                } else {
                    $check = $senderLocked->canDebit($amount);
                    if (! ($check['ok'] ?? false)) {
                        throw new \RuntimeException($check['message'] ?? 'cannot_debit');
                    }
                    $balanceAfter = round((float) $senderLocked->balance - $amount, 2);
                    $senderLocked->balance = $balanceAfter;
                    $senderLocked->daily_transfer_total = round((float) $senderLocked->daily_transfer_total + $amount, 2);
                    $senderLocked->daily_transfer_for_date = now()->toDateString();
                }

                $creditCheck = $recipientLocked->canCredit($amount);
                if (! ($creditCheck['ok'] ?? false)) {
                    throw new \RuntimeException($creditCheck['message'] ?? 'Recipient credit limit exceeded.');
                }

                $recvBalanceBefore = round((float) $recipientLocked->balance, 2);
                $recvBalanceAfter = round($recvBalanceBefore + $amount, 2);
                $recipientLocked->balance = $recvBalanceAfter;

                $senderLocked->pin_failed_attempts = 0;
                $senderLocked->save();
                $recipientLocked->save();

                $debitMeta = array_filter([
                    'bank_name' => $bankName,
                    'channel' => $channel,
                    'narration' => $remark,
                    'payout_mode' => 'internal_va',
                    'payout_pending' => false,
                    'payout_bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
                    'internal_recipient_wallet_id' => (int) $recipientLocked->id,
                ], static fn ($v) => $v !== null && $v !== '');

                $debitTxn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $senderLocked->id,
                    'sender_name' => $senderDisplayName,
                    'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
                    'ledger_scope' => $ledgerScope,
                    'amount' => $amount,
                    'balance_after' => $balanceAfter,
                    'counterparty_account_number' => $acct,
                    'counterparty_bank_code' => $bankCode,
                    'counterparty_account_name' => $beneficiaryName,
                    'external_reference' => $reference,
                    'meta' => $debitMeta,
                ]);
                $debitTransactionId = $debitTxn->id;

                $creditTxn = WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $recipientLocked->id,
                    'sender_name' => $senderDisplayName,
                    'type' => WhatsappWalletTransaction::TYPE_P2P_CREDIT,
                    'amount' => $amount,
                    'balance_after' => $recvBalanceAfter,
                    'counterparty_phone_e164' => $senderLocked->phone_e164,
                    'counterparty_account_number' => $acct,
                    'counterparty_account_name' => $senderDisplayName,
                    'external_reference' => $reference,
                    'meta' => [
                        'channel' => $channel,
                        'internal_va' => true,
                        'sender_wallet_id' => (int) $senderLocked->id,
                        'bank_name' => $bankName,
                    ],
                ]);
                $creditTransactionId = $creditTxn->id;
            });
        } catch (\Throwable $e) {
            Log::warning('whatsapp_wallet.internal_va_transfer_failed', [
                'error' => $e->getMessage(),
                'sender_wallet_id' => $sender->id,
                'recipient_wallet_id' => $recipient->id,
            ]);

            $msg = $e->getMessage();
            if (in_array($msg, ['wallet_missing', 'PIN not set.'], true)
                || str_starts_with($msg, 'Tier 1')
                || $msg === 'Insufficient balance.') {
                return ['ok' => false, 'message' => $msg === 'wallet_missing' ? 'Wallet not found.' : $msg];
            }

            return ['ok' => false, 'message' => 'Transfer could not be completed. Check balance and limits.'];
        }

        return [
            'ok' => true,
            'message' => 'Transfer completed.',
            'bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
            'reference' => $reference,
            'response_message' => 'Internal wallet transfer.',
            'debit_transaction_id' => $debitTransactionId,
            'credit_transaction_id' => $creditTransactionId,
            'balance_after' => $balanceAfter,
            'recipient_wallet_id' => (int) $recipient->id,
            'recv_balance_before' => $recvBalanceBefore,
            'recv_balance_after' => $recvBalanceAfter,
            'internal_va' => true,
        ];
    }
}
