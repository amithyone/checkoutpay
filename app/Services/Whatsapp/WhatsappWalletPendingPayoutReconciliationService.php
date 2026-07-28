<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Models\MevonPayLedgerEntry;
use App\Services\MevonPay\MevonPayLedgerRecorder;
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
        private MevonPayLedgerRecorder $ledgerRecorder,
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

        $alreadyReversed = $transaction->isReversed();

        // Lazy wallet reconcile still skips reversed rows. Admin "Check status" may
        // re-query so UI/meta can catch up when MevonPay later reports success.
        if ($alreadyReversed && $onlyIfPending) {
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
        $payoutApi = $this->resolvePayoutApi($meta);

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

        $meta = $this->applyStatusToMeta($transaction, $result, $newBucket, $adminId, $alreadyReversed);

        $transaction->update(['meta' => $meta]);

        $this->syncLedgerPayoutBucket($transaction->fresh() ?? $transaction, $reference, $payoutApi, $meta);

        $fresh = $transaction->fresh() ?? $transaction;
        $failedConfirmations = (int) ((is_array($fresh->meta) ? $fresh->meta : [])['provider_failed_confirmations'] ?? 0);
        $requiredConfirmations = $this->failedConfirmationsRequired();

        // Never auto-refund again after a prior reverse (even if TSQ still says failed).
        $autoRefund = $alreadyReversed
            ? null
            : $this->settleIfTerminal($fresh, $newBucket, $adminId, $failedConfirmations, $requiredConfirmations);
        if ($autoRefund !== null) {
            $result['auto_refund'] = $autoRefund;
        } elseif (
            ! $alreadyReversed
            && $newBucket === MavonPayTransferService::BUCKET_FAILED
            && $failedConfirmations < $requiredConfirmations
        ) {
            $result['message'] = sprintf(
                'Provider reported failed (%d/%d confirmations). Waiting for another status check before reversing funds.',
                $failedConfirmations,
                $requiredConfirmations,
            );
            $result['awaiting_failed_confirmations'] = true;
            $result['provider_failed_confirmations'] = $failedConfirmations;
            $result['provider_failed_confirmations_required'] = $requiredConfirmations;
        }

        if ($alreadyReversed && $newBucket === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            $result['message'] = 'Provider reports successful, but this payout was already reversed to the customer. Manual recovery may be required.';
            $result['reversal_conflict'] = true;
        }

        $result['checked'] = true;
        $result['skipped'] = false;
        $result['payout_bucket'] = $fresh->fresh()->payoutBucketLabel();
        $result['provider_failed_confirmations'] = $failedConfirmations;
        $result['provider_failed_confirmations_required'] = $requiredConfirmations;

        return $result;
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

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function applyStatusToMeta(
        WhatsappWalletTransaction $transaction,
        array $result,
        string $newBucket,
        ?int $adminId,
        bool $alreadyReversed = false,
    ): array {
        $meta = is_array($transaction->meta) ? $transaction->meta : [];

        $meta['provider_status_checked_at'] = now()->toIso8601String();
        $meta['provider_status_bucket'] = $newBucket;
        $meta['provider_status_response_code'] = $result['response_code'] ?? null;
        $meta['provider_status_response_message'] = $result['response_message'] ?? null;
        $meta['provider_status_http_status'] = $result['http_status'] ?? null;

        $requiredConfirmations = $this->failedConfirmationsRequired();
        $failedConfirmations = (int) ($meta['provider_failed_confirmations'] ?? 0);

        if ($newBucket === MavonPayTransferService::BUCKET_FAILED && ! $alreadyReversed) {
            $failedConfirmations++;
            $meta['provider_failed_confirmations'] = $failedConfirmations;
            $meta['provider_failed_confirmation_at'] = now()->toIso8601String();
        } elseif ($newBucket === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            $failedConfirmations = 0;
            $meta['provider_failed_confirmations'] = 0;
        } elseif ($newBucket === MavonPayTransferService::BUCKET_PENDING) {
            // Ambiguous / not-found responses reset the failed streak.
            $failedConfirmations = 0;
            $meta['provider_failed_confirmations'] = 0;
        }

        $terminalFailed = $alreadyReversed
            || ($newBucket === MavonPayTransferService::BUCKET_FAILED
                && $failedConfirmations >= $requiredConfirmations);

        if ($newBucket !== '') {
            if ($newBucket === MavonPayTransferService::BUCKET_FAILED && ! $terminalFailed && ! $alreadyReversed) {
                // Keep funds locked as pending until failure is confirmed enough times.
                $meta['payout_bucket'] = MavonPayTransferService::BUCKET_PENDING;
                $meta['payout_pending'] = true;
                $meta['payout_failed'] = false;
                $meta['provider_reported_failed'] = true;
            } else {
                $meta['payout_bucket'] = $newBucket;
                $meta['payout_pending'] = $newBucket === MavonPayTransferService::BUCKET_PENDING;
                // Keep payout_failed true only for confirmed failures (or prior reverse without success).
                $meta['payout_failed'] = $alreadyReversed
                    ? $newBucket !== MavonPayTransferService::BUCKET_SUCCESSFUL
                    : $newBucket === MavonPayTransferService::BUCKET_FAILED;
                if ($newBucket !== MavonPayTransferService::BUCKET_FAILED) {
                    unset($meta['provider_reported_failed']);
                }
            }
        }

        if ($alreadyReversed && $newBucket === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            $meta['provider_success_after_reversal'] = true;
            $meta['provider_success_after_reversal_at'] = now()->toIso8601String();
        }

        if (! empty($result['response_code'])) {
            $meta['payout_response_code'] = $result['response_code'];
        }
        if (! empty($result['response_message'])) {
            $meta['payout_response_message'] = $result['response_message'];
        }

        $storedBucket = (string) ($meta['payout_bucket'] ?? $newBucket);
        $bucketForPayload = $storedBucket !== '' ? $storedBucket : $transaction->payoutBucketLabel();
        $refundedFlag = $alreadyReversed
            || ($bucketForPayload === MavonPayTransferService::BUCKET_FAILED && $terminalFailed);

        $existingMevon = is_array($meta['mevonpay'] ?? null) ? $meta['mevonpay'] : null;
        $existingPayoutApi = is_string($meta['payout_api'] ?? null) && $meta['payout_api'] !== ''
            ? (string) $meta['payout_api']
            : (is_array($existingMevon) ? (string) ($existingMevon['payout_api'] ?? '') : '');

        $meta['mevonpay'] = MevonPayPayoutMetaNormalizer::buildPayload(
            array_merge($result, [
                'bucket' => $bucketForPayload,
                'payout_api' => $existingPayoutApi !== '' ? $existingPayoutApi : ($result['payout_api'] ?? null),
            ]),
            $bucketForPayload,
            $refundedFlag,
        );
        if (is_array($existingMevon)) {
            $meta['mevonpay']['initial_payout'] = $existingMevon['initial_payout'] ?? $existingMevon;
        }
        if ($existingPayoutApi !== '') {
            $meta['payout_api'] = $existingPayoutApi;
            $meta['mevonpay']['payout_api'] = $existingPayoutApi;
        }

        $source = $adminId !== null ? 'provider_status_api' : 'lazy_reconcile';
        $meta['mevonpay']['last_provider_check'] = array_merge(
            MevonPayPayoutMetaNormalizer::buildPayload($result, $bucketForPayload, $refundedFlag),
            ['checked_at' => now()->toIso8601String(), 'source' => $source],
        );

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function syncLedgerPayoutBucket(
        WhatsappWalletTransaction $transaction,
        string $reference,
        ?string $payoutApi,
        array $meta,
    ): void {
        $bucket = (string) ($meta['payout_bucket'] ?? '');
        if ($bucket === '') {
            return;
        }

        $amount = abs((float) $transaction->amount);
        if ($amount <= 0) {
            return;
        }

        $api = $payoutApi !== null && $payoutApi !== ''
            ? $payoutApi
            : (string) ($meta['payout_api'] ?? MevonPayLedgerEntry::PAYOUT_API_CREATETRANSFER);

        $this->ledgerRecorder->recordOutbound(
            MevonPayLedgerEntry::FLOW_WHATSAPP_BANK_TRANSFER,
            $amount,
            $reference,
            $api,
            $bucket,
            null,
            $transaction,
            ['provider_status_sync' => true],
        );
    }

    /**
     * @return array{ok: bool, message: string}|null
     */
    private function settleIfTerminal(
        WhatsappWalletTransaction $transaction,
        string $newBucket,
        ?int $adminId,
        int $failedConfirmations,
        int $requiredConfirmations,
    ): ?array {
        if ($newBucket === MavonPayTransferService::BUCKET_PENDING) {
            return null;
        }

        if ($newBucket !== MavonPayTransferService::BUCKET_FAILED || $transaction->isReversed()) {
            return null;
        }

        if ($failedConfirmations < $requiredConfirmations) {
            Log::info('whatsapp.wallet.payout_failed_awaiting_confirmations', [
                'transaction_id' => $transaction->id,
                'confirmations' => $failedConfirmations,
                'required' => $requiredConfirmations,
            ]);

            return null;
        }

        return $this->refundService->refundTransaction(
            $transaction,
            $adminId,
            'provider_status_failed',
        );
    }

    private function failedConfirmationsRequired(): int
    {
        return max(2, (int) config('whatsapp.wallet.payout_failed_confirmations_required', 2));
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
