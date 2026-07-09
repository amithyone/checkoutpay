<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use App\Services\MavonPayTransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Credits wallet balance when a bank payout failed or is reversed by admin.
 * Credits personal balance or business ledger based on the transaction's ledger_scope.
 */
class WhatsappWalletBankPayoutRefundService
{
    public function __construct(
        private WhatsappWalletTopupNotifier $walletNotifier,
        private ConsumerBusinessWalletLedgerService $businessLedger,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function refundTransaction(
        WhatsappWalletTransaction $transaction,
        ?int $adminId = null,
        string $reason = 'admin_manual_refund',
    ): array {
        if ($transaction->type !== WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT) {
            return ['ok' => false, 'message' => 'Only bank transfer transactions can be refunded.'];
        }

        if ($transaction->isReversed()) {
            return ['ok' => false, 'message' => 'This transaction was already reversed.'];
        }

        $amount = round(abs((float) $transaction->amount), 2);
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Invalid transaction amount.'];
        }

        $refunded = false;
        $balanceAfter = null;
        $walletId = (int) $transaction->whatsapp_wallet_id;
        $ledgerScope = ConsumerWalletTransactionScope::normalize(
            (string) ($transaction->ledger_scope ?? ConsumerWalletTransactionScope::SCOPE_PERSONAL)
        );
        $creditedBusiness = false;

        DB::transaction(function () use (
            $transaction,
            $amount,
            $adminId,
            $reason,
            $ledgerScope,
            &$refunded,
            &$balanceAfter,
            &$creditedBusiness,
        ): void {
            $txn = WhatsappWalletTransaction::query()->lockForUpdate()->find($transaction->id);
            if (! $txn || $txn->isReversed()) {
                return;
            }

            $wallet = WhatsappWallet::query()->lockForUpdate()->find($txn->whatsapp_wallet_id);
            if (! $wallet) {
                return;
            }

            $scope = ConsumerWalletTransactionScope::normalize(
                (string) ($txn->ledger_scope ?? $ledgerScope)
            );

            if ($scope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
                $credit = $this->businessLedger->creditLockedWallet($wallet, $amount);
                if (! ($credit['ok'] ?? false)) {
                    Log::error('whatsapp.wallet.admin_refund_business_failed', [
                        'transaction_id' => $txn->id,
                        'wallet_id' => $wallet->id,
                        'amount' => $amount,
                        'message' => $credit['message'] ?? null,
                    ]);

                    return;
                }
                $balanceAfter = (float) ($credit['balance_after'] ?? $this->businessLedger->resolvedBalance($wallet));
                $creditedBusiness = true;
            } else {
                $wallet->balance = round((float) $wallet->balance + $amount, 2);
                $wallet->daily_transfer_total = max(0, round((float) $wallet->daily_transfer_total - $amount, 2));
                $wallet->save();
                $balanceAfter = (float) $wallet->balance;
            }

            $meta = is_array($txn->meta) ? $txn->meta : [];
            $meta['reversed_at'] = now()->toIso8601String();
            $meta['payout_pending'] = false;
            $meta['payout_failed'] = true;
            $meta['payout_bucket'] = MavonPayTransferService::BUCKET_FAILED;
            $meta['admin_refund_reason'] = $reason;
            $meta['refund_ledger_scope'] = $scope;
            if ($adminId !== null) {
                $meta['admin_refund_by'] = $adminId;
            }

            $txn->update(['meta' => $meta]);
            $refunded = true;
        });

        if (! $refunded) {
            return ['ok' => false, 'message' => 'Refund could not be applied (already reversed, missing wallet, or business credit failed).'];
        }

        Log::info('whatsapp.wallet.admin_refund', [
            'transaction_id' => $transaction->id,
            'wallet_id' => $transaction->whatsapp_wallet_id,
            'amount' => $amount,
            'admin_id' => $adminId,
            'reason' => $reason,
            'ledger_scope' => $ledgerScope,
        ]);

        if ($balanceAfter !== null && ! $creditedBusiness) {
            $wallet = WhatsappWallet::query()->find($walletId);
            if ($wallet) {
                $this->walletNotifier->notifyMoneyReceived($wallet, $amount, $balanceAfter, null, [
                    'credit_source' => 'payout_refund',
                ]);
            }
        }

        $scopeLabel = $creditedBusiness ? 'business' : 'personal';

        return [
            'ok' => true,
            'message' => "Wallet credited ({$scopeLabel}) and transaction marked as reversed.",
        ];
    }
}
