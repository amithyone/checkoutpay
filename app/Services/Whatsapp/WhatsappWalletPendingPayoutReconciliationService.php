<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\MevonPay\MevonPayPayoutMetaNormalizer;
use App\Services\MevonPay\MevonPayTransferStatusService;
use App\Services\MavonPayTransferService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Lazy MevonPay TSQ for pending bank payouts (48h window) and one-time settlement.
 */
class WhatsappWalletPendingPayoutReconciliationService
{
    public function __construct(
        private MevonPayTransferStatusService $transferStatus,
        private WhatsappWalletBankPayoutRefundService $refundService,
    ) {}

    /**
     * Reconcile pending bank payouts for a wallet (on balance refresh / wallet menu).
     *
     * @return array{
     *     checked: int,
     *     skipped: int,
     *     refunds: list<array{transaction_id: int, amount: float, message: string}>
     * }
     */
    public function reconcileWallet(WhatsappWallet $wallet): array
    {
        if (! $this->transferStatus->isAvailable()) {
            return ['checked' => 0, 'skipped' => 0, 'refunds' => []];
        }

        $hours = max(1, (int) config('whatsapp.wallet.payout_reconcile_hours', 48));
        $max = max(1, (int) config('whatsapp.wallet.payout_reconcile_max_per_trigger', 3));

        $pending = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->bankTransferOut()
            ->payoutPending()
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at')
            ->limit($max)
            ->get();

        if ($pending->isEmpty()) {
            return ['checked' => 0, 'skipped' => 0, 'refunds' => []];
        }

        $checked = 0;
        $skipped = 0;
        $refunds = [];

        foreach ($pending as $txn) {
            $result = $this->reconcileTransaction($txn, null, onlyIfPending: true);
            if ($result['skipped'] ?? false) {
                $skipped++;
            } elseif ($result['checked'] ?? false) {
                $checked++;
            }
            if (($result['auto_refund']['ok'] ?? false) === true) {
                $refunds[] = [
                    'transaction_id' => $txn->id,
                    'amount' => round(abs((float) $txn->amount), 2),
                    'message' => (string) ($result['auto_refund']['message'] ?? ''),
                ];
            }
        }

        return ['checked' => $checked, 'skipped' => $skipped, 'refunds' => $refunds];
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcileTransaction(
        WhatsappWalletTransaction $transaction,
        ?int $adminId = null,
        bool $onlyIfPending = false,
    ): array {
        if (! $this->transferStatus->isAvailable()) {
            return [
                'available' => false,
                'message' => 'MevonPay transfer status API is not configured.',
                'skipped' => true,
            ];
        }

        if ($transaction->type !== WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT) {
            return [
                'available' => false,
                'message' => 'Only bank transfer transactions can be reconciled.',
                'skipped' => true,
            ];
        }

        if ($transaction->isReversed()) {
            return [
                'available' => true,
                'message' => 'Transaction already reversed.',
                'skipped' => true,
            ];
        }

        if ($onlyIfPending && $transaction->payoutBucketLabel() !== MavonPayTransferService::BUCKET_PENDING) {
            return [
                'available' => true,
                'message' => 'Transaction is not pending.',
                'skipped' => true,
            ];
        }

        if ($onlyIfPending && $adminId === null && $this->wasCheckedRecently($transaction)) {
            return [
                'available' => true,
                'message' => 'Skipped (checked recently).',
                'skipped' => true,
            ];
        }

        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        $reference = (string) ($transaction->external_reference ?? $meta['payout_reference'] ?? '');
        $payoutApi = isset($meta['payout_api']) ? (string) $meta['payout_api'] : null;

        if ($reference === '') {
            return [
                'available' => false,
                'message' => 'Missing transfer reference.',
                'skipped' => true,
            ];
        }

        $result = $this->transferStatus->checkStatus($reference, $payoutApi);

        if (! ($result['available'] ?? false)) {
            return $result;
        }

        $transaction = $transaction->fresh() ?? $transaction;
        $newBucket = (string) ($result['bucket'] ?? $transaction->payoutBucketLabel());

        $meta = $this->applyStatusToMeta($transaction, $result, $newBucket, $adminId);

        $transaction->update(['meta' => $meta]);

        $autoRefund = $this->settleIfTerminal($transaction->fresh(), $newBucket, $adminId);
        if ($autoRefund !== null) {
            $result['auto_refund'] = $autoRefund;
        }

        $result['checked'] = true;
        $result['skipped'] = false;
        $result['payout_bucket'] = $transaction->fresh()->payoutBucketLabel();

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function applyStatusToMeta(
        WhatsappWalletTransaction $transaction,
        array $result,
        string $newBucket,
        ?int $adminId,
    ): array {
        $meta = is_array($transaction->meta) ? $transaction->meta : [];

        $meta['provider_status_checked_at'] = now()->toIso8601String();
        $meta['provider_status_bucket'] = $newBucket;
        $meta['provider_status_response_code'] = $result['response_code'] ?? null;
        $meta['provider_status_response_message'] = $result['response_message'] ?? null;
        $meta['provider_status_http_status'] = $result['http_status'] ?? null;

        if ($newBucket !== '') {
            $meta['payout_bucket'] = $newBucket;
            $meta['payout_pending'] = $newBucket === MavonPayTransferService::BUCKET_PENDING;
            $meta['payout_failed'] = $newBucket === MavonPayTransferService::BUCKET_FAILED;
        }

        $bucketForPayload = $newBucket !== '' ? $newBucket : $transaction->payoutBucketLabel();
        $refundedFlag = $transaction->isReversed()
            || $bucketForPayload === MavonPayTransferService::BUCKET_FAILED;

        $existingMevon = is_array($meta['mevonpay'] ?? null) ? $meta['mevonpay'] : null;
        $meta['mevonpay'] = MevonPayPayoutMetaNormalizer::buildPayload(
            array_merge($result, ['bucket' => $bucketForPayload]),
            $bucketForPayload,
            $refundedFlag,
        );
        if (is_array($existingMevon)) {
            $meta['mevonpay']['initial_payout'] = $existingMevon['initial_payout'] ?? $existingMevon;
        }

        $source = $adminId !== null ? 'provider_status_api' : 'lazy_reconcile';
        $meta['mevonpay']['last_provider_check'] = array_merge(
            MevonPayPayoutMetaNormalizer::buildPayload($result, $bucketForPayload, $refundedFlag),
            ['checked_at' => now()->toIso8601String(), 'source' => $source],
        );

        return $meta;
    }

    /**
     * @return array{ok: bool, message: string}|null
     */
    private function settleIfTerminal(
        WhatsappWalletTransaction $transaction,
        string $newBucket,
        ?int $adminId,
    ): ?array {
        if ($newBucket === MavonPayTransferService::BUCKET_PENDING) {
            return null;
        }

        if ($newBucket !== MavonPayTransferService::BUCKET_FAILED || $transaction->isReversed()) {
            return null;
        }

        return $this->refundService->refundTransaction(
            $transaction,
            $adminId,
            'provider_status_failed',
        );
    }

    private function wasCheckedRecently(WhatsappWalletTransaction $transaction): bool
    {
        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        $last = $meta['mevonpay']['last_provider_check']['checked_at'] ?? null;
        if (! is_string($last) || $last === '') {
            return false;
        }

        try {
            $checkedAt = Carbon::parse($last);
        } catch (\Throwable) {
            return false;
        }

        $minutes = max(1, (int) config('whatsapp.wallet.payout_reconcile_min_interval_minutes', 5));

        return $checkedAt->greaterThan(now()->subMinutes($minutes));
    }
}
