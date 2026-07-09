<?php

namespace App\Services\MevonPay;

use App\Services\MavonPayTransferService;
use Illuminate\Support\Facades\Log;

/**
 * When an initial payout response is "failed", confirm with MevonPay TSQ before deciding.
 * A single failed TSQ is not enough to refund — keep pending for later multi-check reconcile.
 */
class MevonPayPayoutPreRefundStatusService
{
    public function __construct(
        private MevonPayTransferStatusService $transferStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $payoutResult
     * @return array{
     *     bucket: string,
     *     result: array<string, mixed>,
     *     refund_allowed: bool,
     *     status_checked: bool
     * }
     */
    public function resolveBeforeRefund(array $payoutResult, string $reference): array
    {
        $initialBucket = (string) ($payoutResult['bucket'] ?? MavonPayTransferService::BUCKET_FAILED);

        if ($initialBucket !== MavonPayTransferService::BUCKET_FAILED) {
            return [
                'bucket' => $initialBucket,
                'result' => $payoutResult,
                'refund_allowed' => false,
                'status_checked' => false,
            ];
        }

        if (! empty($payoutResult['provider_response_unknown'])) {
            return $this->pendingWithoutRefund($payoutResult, 'initial_response_unknown');
        }

        if (! $this->transferStatus->isAvailable()) {
            Log::warning('mevonpay.pre_refund_status_skipped', [
                'reference' => $reference,
                'reason' => 'tsq_not_configured',
            ]);

            return $this->pendingWithoutRefund($payoutResult, 'tsq_not_configured');
        }

        $payoutApi = isset($payoutResult['payout_api']) ? (string) $payoutResult['payout_api'] : null;
        $status = $this->transferStatus->checkStatus($reference, $payoutApi);

        if (! ($status['available'] ?? false)) {
            Log::info('mevonpay.pre_refund_status_unavailable', [
                'reference' => $reference,
                'message' => $status['message'] ?? null,
            ]);

            return $this->pendingWithoutRefund(
                $this->mergeStatusIntoResult($payoutResult, $status, checked: false),
                'tsq_unavailable',
            );
        }

        $confirmedBucket = (string) ($status['bucket'] ?? MavonPayTransferService::BUCKET_PENDING);

        if ($confirmedBucket === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            Log::warning('mevonpay.initial_failed_tsq_success', [
                'reference' => $reference,
                'initial_message' => $payoutResult['response_message'] ?? null,
                'tsq_message' => $status['response_message'] ?? null,
            ]);

            $merged = $this->mergeStatusIntoResult($payoutResult, $status, checked: true);
            $merged['bucket'] = MavonPayTransferService::BUCKET_SUCCESSFUL;

            return [
                'bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
                'result' => $merged,
                'refund_allowed' => false,
                'status_checked' => true,
            ];
        }

        // Pending or a single failed TSQ: never refund at payout time.
        // Lazy reconcile / admin check must confirm failure more than once.
        $merged = $this->mergeStatusIntoResult($payoutResult, $status, checked: true);
        $merged['bucket'] = MavonPayTransferService::BUCKET_PENDING;
        $merged['provider_failed_confirmations'] = $confirmedBucket === MavonPayTransferService::BUCKET_FAILED ? 1 : 0;
        $merged['pre_refund_status_check']['held_as_pending'] = true;
        $merged['pre_refund_status_check']['provider_bucket'] = $confirmedBucket;

        if ($confirmedBucket === MavonPayTransferService::BUCKET_FAILED) {
            Log::info('mevonpay.initial_failed_tsq_held_pending', [
                'reference' => $reference,
                'tsq_code' => $status['response_code'] ?? null,
                'tsq_message' => $status['response_message'] ?? null,
            ]);
        }

        return [
            'bucket' => MavonPayTransferService::BUCKET_PENDING,
            'result' => $merged,
            'refund_allowed' => false,
            'status_checked' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payoutResult
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private function mergeStatusIntoResult(array $payoutResult, array $status, bool $checked): array
    {
        $merged = $payoutResult;
        $merged['pre_refund_status_check'] = array_filter([
            'checked_at' => now()->toIso8601String(),
            'checked' => $checked,
            'bucket' => $status['bucket'] ?? null,
            'response_code' => $status['response_code'] ?? null,
            'response_message' => $status['response_message'] ?? null,
            'transaction_status' => $status['transaction_status'] ?? null,
            'http_status' => $status['http_status'] ?? null,
            'message' => $status['message'] ?? null,
        ], static fn ($v) => $v !== null);

        foreach (['response_code', 'response_message', 'raw', 'http_status'] as $key) {
            if (array_key_exists($key, $status) && $status[$key] !== null) {
                $merged[$key] = $status[$key];
            }
        }

        if (isset($status['transaction_status']) && is_string($status['transaction_status'])) {
            $merged['transaction_status'] = $status['transaction_status'];
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $payoutResult
     * @return array{
     *     bucket: string,
     *     result: array<string, mixed>,
     *     refund_allowed: bool,
     *     status_checked: bool
     * }
     */
    private function pendingWithoutRefund(array $payoutResult, string $reason): array
    {
        $result = array_merge($payoutResult, [
            'bucket' => MavonPayTransferService::BUCKET_PENDING,
            'pre_refund_status_check' => [
                'checked_at' => now()->toIso8601String(),
                'checked' => false,
                'reason' => $reason,
            ],
        ]);

        return [
            'bucket' => MavonPayTransferService::BUCKET_PENDING,
            'result' => $result,
            'refund_allowed' => false,
            'status_checked' => false,
        ];
    }
}
