<?php

namespace App\Services\Consumer;

use App\Models\Payment;
use App\Models\WhatsappWalletTransaction;
use App\Services\MavonPayTransferService;

/**
 * Ensures native-app transaction rows expose status in fields the app already reads:
 * top-level status/state/failed/payout_failed and meta.status / payout_status / failed / etc.
 */
final class ConsumerWalletTransactionStatusNormalizer
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function apply(array $row, ?WhatsappWalletTransaction $tx = null): array
    {
        $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
        $type = (string) ($row['type'] ?? $tx?->type ?? '');

        $resolved = $this->resolve($type, $meta, $tx);
        $status = $resolved['status'];
        $failed = $status === self::STATUS_FAILED;
        $displayStatus = $resolved['display_status'];

        $row['status'] = $displayStatus;
        $row['state'] = $displayStatus;
        $row['failed'] = $failed;
        if ($type === WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT || $type === 'merchant_withdrawal_out') {
            $row['payout_failed'] = $failed;
        }

        $meta['status'] = $displayStatus;
        $meta['state'] = $displayStatus;
        $meta['failed'] = $failed;

        if ($type === WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT) {
            $meta['payout_failed'] = $failed;
            $meta['payout_status'] = $displayStatus;
            $meta['transfer_status'] = $displayStatus;
            $meta['payment_status'] = $displayStatus;
            // Keep legacy flags in sync for older clients / admin tools.
            $meta['payout_pending'] = $status === self::STATUS_PENDING;
            if ($status === self::STATUS_FAILED) {
                $meta['payout_bucket'] = MavonPayTransferService::BUCKET_FAILED;
            } elseif ($status === self::STATUS_PENDING) {
                $meta['payout_bucket'] = MavonPayTransferService::BUCKET_PENDING;
            } elseif (($meta['payout_bucket'] ?? '') === '' || ($meta['payout_bucket'] ?? '') === 'unknown') {
                $meta['payout_bucket'] = MavonPayTransferService::BUCKET_SUCCESSFUL;
            }
        }

        if ($type === 'merchant_withdrawal_out') {
            $meta['payout_failed'] = $failed;
            $meta['payout_status'] = $meta['payout_status'] ?? $displayStatus;
            $meta['payment_status'] = $displayStatus;
        }

        if ($type === 'merchant_payment_in') {
            $meta['payment_status'] = $displayStatus;
        }

        $row['meta'] = $meta;

        return $row;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{status: string, display_status: string}
     */
    private function resolve(string $type, array $meta, ?WhatsappWalletTransaction $tx): array
    {
        if ($type === WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT) {
            $bucket = $tx?->payoutBucketLabel() ?? $this->bucketFromMeta($meta);
            if ($bucket === MavonPayTransferService::BUCKET_PENDING) {
                return ['status' => self::STATUS_PENDING, 'display_status' => self::STATUS_PENDING];
            }
            if ($bucket === MavonPayTransferService::BUCKET_FAILED) {
                return ['status' => self::STATUS_FAILED, 'display_status' => self::STATUS_FAILED];
            }

            return ['status' => self::STATUS_SUCCESS, 'display_status' => self::STATUS_SUCCESS];
        }

        if ($this->isVtuType($type)) {
            if (! empty($meta['vtu_refunded']) || $this->tokenIsFailed((string) ($meta['vtu_status'] ?? ''))) {
                return ['status' => self::STATUS_FAILED, 'display_status' => self::STATUS_FAILED];
            }
            if (! empty($meta['vtu_pending']) || $this->tokenIsPending((string) ($meta['vtu_status'] ?? ''))) {
                return ['status' => self::STATUS_PENDING, 'display_status' => self::STATUS_PENDING];
            }

            return ['status' => self::STATUS_SUCCESS, 'display_status' => self::STATUS_SUCCESS];
        }

        if ($type === 'merchant_payment_in') {
            $raw = strtolower(trim((string) ($meta['status'] ?? '')));
            if ($raw === Payment::STATUS_PENDING || $this->tokenIsPending($raw)) {
                return ['status' => self::STATUS_PENDING, 'display_status' => $raw !== '' ? $raw : self::STATUS_PENDING];
            }
            if ($raw === Payment::STATUS_REJECTED || $this->tokenIsFailed($raw)) {
                return ['status' => self::STATUS_FAILED, 'display_status' => $raw !== '' ? $raw : self::STATUS_FAILED];
            }
            // Keep approved (and similar) as merchant tokens the app already maps.
            $display = $raw !== '' ? $raw : Payment::STATUS_APPROVED;

            return [
                'status' => self::STATUS_SUCCESS,
                'display_status' => $display,
            ];
        }

        if ($type === 'merchant_withdrawal_out') {
            $raw = strtolower(trim((string) ($meta['status'] ?? '')));
            $payout = strtolower(trim((string) ($meta['payout_status'] ?? '')));
            if ($raw === 'pending' || $this->tokenIsPending($raw) || $this->tokenIsPending($payout)) {
                return ['status' => self::STATUS_PENDING, 'display_status' => $raw !== '' ? $raw : self::STATUS_PENDING];
            }
            if ($raw === 'rejected' || $this->tokenIsFailed($raw) || $this->tokenIsFailed($payout)) {
                return ['status' => self::STATUS_FAILED, 'display_status' => $raw !== '' ? $raw : self::STATUS_FAILED];
            }
            // pending → approved → processed: surface existing token when present.
            $display = $raw !== '' ? $raw : self::STATUS_SUCCESS;

            return [
                'status' => self::STATUS_SUCCESS,
                'display_status' => $display,
            ];
        }

        if ($type === WhatsappWalletTransaction::TYPE_BUSINESS_RUBIES_IN) {
            return ['status' => self::STATUS_SUCCESS, 'display_status' => Payment::STATUS_APPROVED];
        }

        // Explicit meta already present (prefer existing tokens).
        $existing = strtolower(trim((string) ($meta['status'] ?? $meta['state'] ?? $meta['payment_status'] ?? '')));
        if ($existing !== '') {
            if ($this->tokenIsPending($existing)) {
                return ['status' => self::STATUS_PENDING, 'display_status' => $existing];
            }
            if ($this->tokenIsFailed($existing) || ! empty($meta['failed']) || ! empty($meta['payout_failed'])) {
                return ['status' => self::STATUS_FAILED, 'display_status' => $existing];
            }

            return ['status' => self::STATUS_SUCCESS, 'display_status' => $existing];
        }

        if (! empty($meta['failed']) || ! empty($meta['payout_failed'])) {
            return ['status' => self::STATUS_FAILED, 'display_status' => self::STATUS_FAILED];
        }
        if (! empty($meta['payout_pending'])) {
            return ['status' => self::STATUS_PENDING, 'display_status' => self::STATUS_PENDING];
        }

        return ['status' => self::STATUS_SUCCESS, 'display_status' => self::STATUS_SUCCESS];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function bucketFromMeta(array $meta): string
    {
        $bucket = (string) ($meta['payout_bucket'] ?? '');
        if ($bucket === MavonPayTransferService::BUCKET_SUCCESSFUL
            || $bucket === MavonPayTransferService::BUCKET_PENDING
            || $bucket === MavonPayTransferService::BUCKET_FAILED) {
            return $bucket;
        }
        if (! empty($meta['payout_failed'])) {
            return MavonPayTransferService::BUCKET_FAILED;
        }
        if (! empty($meta['payout_pending'])) {
            return MavonPayTransferService::BUCKET_PENDING;
        }

        return MavonPayTransferService::BUCKET_SUCCESSFUL;
    }

    private function isVtuType(string $type): bool
    {
        return in_array($type, [
            WhatsappWalletTransaction::TYPE_VTU_AIRTIME,
            WhatsappWalletTransaction::TYPE_VTU_DATA,
            WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY,
            WhatsappWalletTransaction::TYPE_VTU_CABLE,
            WhatsappWalletTransaction::TYPE_VTU_BETTING,
        ], true);
    }

    private function tokenIsPending(string $token): bool
    {
        return in_array($token, ['pending', 'processing', 'queued', 'in_progress', 'awaiting'], true);
    }

    private function tokenIsFailed(string $token): bool
    {
        return in_array($token, ['failed', 'fail', 'rejected', 'cancelled', 'canceled', 'reversed', 'error'], true);
    }
}
