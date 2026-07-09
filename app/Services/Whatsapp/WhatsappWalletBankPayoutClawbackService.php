<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use App\Services\MevonPay\MevonPayTransferStatusService;
use App\Services\MavonPayTransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Debits a wallet after a false auto-refund when MevonPay later confirms the payout succeeded.
 */
class WhatsappWalletBankPayoutClawbackService
{
    public function __construct(
        private MevonPayTransferStatusService $transferStatus,
        private ConsumerBusinessWalletLedgerService $businessLedger,
    ) {}

    /**
     * @return array{ok: bool, message: string, provider_bucket?: string, ledger_scope?: string}
     */
    public function clawbackTransaction(
        WhatsappWalletTransaction $transaction,
        ?int $adminId = null,
        bool $skipProviderCheck = false,
    ): array {
        if ($transaction->type !== WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT) {
            return ['ok' => false, 'message' => 'Only bank transfer transactions can be clawed back.'];
        }

        if (! $transaction->isReversed()) {
            return ['ok' => false, 'message' => 'Transaction is not reversed — nothing to claw back.'];
        }

        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        if (! empty($meta['clawback_at'])) {
            return ['ok' => false, 'message' => 'This false refund was already clawed back.'];
        }

        $amount = round(abs((float) $transaction->amount), 2);
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Invalid transaction amount.'];
        }

        $providerBucket = null;
        if (! $skipProviderCheck) {
            if (! $this->transferStatus->isAvailable()) {
                return ['ok' => false, 'message' => 'MevonPay transfer status API is not configured.'];
            }

            $reference = (string) ($transaction->external_reference ?? $meta['payout_reference'] ?? '');
            if ($reference === '') {
                return ['ok' => false, 'message' => 'Missing transfer reference for provider confirmation.'];
            }

            $payoutApi = $this->resolvePayoutApi($meta);
            $status = $this->transferStatus->checkStatus($reference, $payoutApi);
            if (! ($status['available'] ?? false)) {
                return [
                    'ok' => false,
                    'message' => 'Could not confirm provider status: '.((string) ($status['message'] ?? 'unavailable')),
                ];
            }

            $providerBucket = (string) ($status['bucket'] ?? '');
            if ($providerBucket !== MavonPayTransferService::BUCKET_SUCCESSFUL) {
                return [
                    'ok' => false,
                    'message' => 'Provider does not report successful (bucket: '.($providerBucket !== '' ? $providerBucket : 'unknown').'). Clawback refused.',
                    'provider_bucket' => $providerBucket !== '' ? $providerBucket : 'unknown',
                ];
            }
        }

        $ledgerScope = ConsumerWalletTransactionScope::normalize(
            (string) ($meta['refund_ledger_scope']
                ?? $transaction->ledger_scope
                ?? ConsumerWalletTransactionScope::SCOPE_PERSONAL)
        );

        $applied = false;
        $balanceAfter = null;

        DB::transaction(function () use (
            $transaction,
            $amount,
            $adminId,
            $ledgerScope,
            $providerBucket,
            &$applied,
            &$balanceAfter,
        ): void {
            $txn = WhatsappWalletTransaction::query()->lockForUpdate()->find($transaction->id);
            if (! $txn || ! $txn->isReversed()) {
                return;
            }

            $currentMeta = is_array($txn->meta) ? $txn->meta : [];
            if (! empty($currentMeta['clawback_at'])) {
                return;
            }

            $wallet = WhatsappWallet::query()->lockForUpdate()->find($txn->whatsapp_wallet_id);
            if (! $wallet) {
                return;
            }

            $scope = ConsumerWalletTransactionScope::normalize(
                (string) ($currentMeta['refund_ledger_scope'] ?? $txn->ledger_scope ?? $ledgerScope)
            );

            if ($scope === ConsumerWalletTransactionScope::SCOPE_BUSINESS) {
                $debit = $this->businessLedger->debitLockedWallet($wallet, $amount);
                if (! ($debit['ok'] ?? false)) {
                    Log::error('whatsapp.wallet.clawback_business_failed', [
                        'transaction_id' => $txn->id,
                        'wallet_id' => $wallet->id,
                        'amount' => $amount,
                        'message' => $debit['message'] ?? null,
                    ]);

                    return;
                }
                $balanceAfter = (float) ($debit['balance_after'] ?? $this->businessLedger->resolvedBalance($wallet));
            } else {
                $personal = round((float) $wallet->balance, 2);
                if ($personal + 0.00001 < $amount) {
                    Log::error('whatsapp.wallet.clawback_insufficient_personal', [
                        'transaction_id' => $txn->id,
                        'wallet_id' => $wallet->id,
                        'amount' => $amount,
                        'balance' => $personal,
                    ]);

                    return;
                }
                $wallet->balance = round($personal - $amount, 2);
                $wallet->daily_transfer_total = round((float) $wallet->daily_transfer_total + $amount, 2);
                $wallet->save();
                $balanceAfter = (float) $wallet->balance;
            }

            $currentMeta['clawback_at'] = now()->toIso8601String();
            $currentMeta['clawback_amount'] = $amount;
            $currentMeta['clawback_ledger_scope'] = $scope;
            $currentMeta['clawback_reason'] = 'provider_success_after_false_refund';
            if ($adminId !== null) {
                $currentMeta['clawback_by'] = $adminId;
            }
            if ($providerBucket !== null) {
                $currentMeta['clawback_provider_bucket'] = $providerBucket;
            }

            // Keep audit of the mistaken reverse, but mark payout successful again.
            $currentMeta['false_reversed_at'] = $currentMeta['reversed_at'] ?? null;
            unset($currentMeta['reversed_at']);
            $currentMeta['payout_pending'] = false;
            $currentMeta['payout_failed'] = false;
            $currentMeta['payout_bucket'] = MavonPayTransferService::BUCKET_SUCCESSFUL;
            $currentMeta['provider_status_bucket'] = MavonPayTransferService::BUCKET_SUCCESSFUL;
            $currentMeta['provider_success_after_reversal'] = true;

            $txn->update(['meta' => $currentMeta]);
            $applied = true;
        });

        if (! $applied) {
            return [
                'ok' => false,
                'message' => 'Clawback could not be applied (insufficient balance, already clawed back, or missing wallet).',
                'ledger_scope' => $ledgerScope,
            ];
        }

        Log::info('whatsapp.wallet.false_refund_clawback', [
            'transaction_id' => $transaction->id,
            'wallet_id' => $transaction->whatsapp_wallet_id,
            'amount' => $amount,
            'admin_id' => $adminId,
            'ledger_scope' => $ledgerScope,
            'balance_after' => $balanceAfter,
        ]);

        return [
            'ok' => true,
            'message' => "Clawed back ₦".number_format($amount, 2)." from {$ledgerScope} wallet. Payout marked successful.",
            'provider_bucket' => $providerBucket ?? MavonPayTransferService::BUCKET_SUCCESSFUL,
            'ledger_scope' => $ledgerScope,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolvePayoutApi(array $meta): ?string
    {
        foreach ([
            $meta['payout_api'] ?? null,
            is_array($meta['mevonpay'] ?? null) ? ($meta['mevonpay']['payout_api'] ?? null) : null,
            is_array($meta['mevonpay']['initial_payout'] ?? null)
                ? ($meta['mevonpay']['initial_payout']['payout_api'] ?? null)
                : null,
        ] as $candidate) {
            $api = trim((string) $candidate);
            if ($api !== '') {
                return $api;
            }
        }

        return null;
    }
}
