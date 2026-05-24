<?php

namespace App\Services\MevonPay;

use App\Models\MevonPayLedgerEntry;
use App\Models\Payment;
use App\Models\WhatsappWalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\MavonPayTransferService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

final class MevonPayLedgerRecorder
{
    public function __construct(
        private MevonPayFeeCalculator $fees,
    ) {}

    /**
    * @param  array<string, mixed>  $meta
    */
    public function recordInbound(
        string $flowType,
        float $grossAmount,
        ?string $externalReference,
        ?string $accountNumber,
        ?Model $source = null,
        array $meta = [],
        ?Carbon $occurredAt = null,
    ): ?MevonPayLedgerEntry {
        if ($grossAmount <= 0) {
            return null;
        }

        $ref = $this->normalizeReference($externalReference);
        if ($ref !== null && $this->inboundExists($ref)) {
            return null;
        }

        $breakdown = $this->fees->inboundBreakdown($grossAmount);

        try {
            return MevonPayLedgerEntry::query()->create([
                'direction' => MevonPayLedgerEntry::DIRECTION_INBOUND,
                'flow_type' => $flowType,
                'gross_amount' => round($grossAmount, 2),
                'mevon_inbound_fee' => $breakdown['inbound_fee'],
                'mevon_outbound_fee' => null,
                'net_mevon_impact' => $breakdown['net_mevon_impact'],
                'external_reference' => $ref,
                'payout_reference' => null,
                'account_number' => $accountNumber !== null && $accountNumber !== '' ? $accountNumber : null,
                'source_type' => $source !== null ? $source->getMorphClass() : null,
                'source_id' => $source?->getKey(),
                'payout_api' => null,
                'payout_bucket' => null,
                'meta' => $meta !== [] ? $meta : null,
                'occurred_at' => $occurredAt ?? now(),
            ]);
        } catch (\Throwable $e) {
            if ($this->isDuplicateKey($e) && $ref !== null) {
                return null;
            }
            Log::warning('mevonpay.ledger.inbound_failed', [
                'flow_type' => $flowType,
                'reference' => $ref,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
    * @param  array<string, mixed>  $meta
    */
    public function recordOutbound(
        string $flowType,
        float $grossAmount,
        string $payoutReference,
        string $payoutApi,
        string $payoutBucket,
        ?string $accountNumber = null,
        ?Model $source = null,
        array $meta = [],
        ?Carbon $occurredAt = null,
    ): ?MevonPayLedgerEntry {
        if ($grossAmount <= 0 && $payoutBucket !== MavonPayTransferService::BUCKET_FAILED) {
            return null;
        }

        $ref = trim($payoutReference);
        if ($ref === '') {
            return null;
        }

        if ($this->outboundExists($ref)) {
            return $this->updateOutboundBucket($ref, $payoutBucket, $meta);
        }

        $chargeApiFee = in_array($payoutBucket, [
            MavonPayTransferService::BUCKET_SUCCESSFUL,
            MavonPayTransferService::BUCKET_PENDING,
        ], true);

        $breakdown = $this->fees->outboundBreakdown($grossAmount, $chargeApiFee);

        try {
            return MevonPayLedgerEntry::query()->create([
                'direction' => MevonPayLedgerEntry::DIRECTION_OUTBOUND,
                'flow_type' => $flowType,
                'gross_amount' => round($grossAmount, 2),
                'mevon_inbound_fee' => null,
                'mevon_outbound_fee' => $breakdown['outbound_fee'],
                'net_mevon_impact' => $breakdown['net_mevon_impact'],
                'external_reference' => null,
                'payout_reference' => $ref,
                'account_number' => $accountNumber !== null && $accountNumber !== '' ? $accountNumber : null,
                'source_type' => $source !== null ? $source->getMorphClass() : null,
                'source_id' => $source?->getKey(),
                'payout_api' => $payoutApi,
                'payout_bucket' => $payoutBucket,
                'meta' => $meta !== [] ? $meta : null,
                'occurred_at' => $occurredAt ?? now(),
            ]);
        } catch (\Throwable $e) {
            if ($this->isDuplicateKey($e)) {
                return $this->updateOutboundBucket($ref, $payoutBucket, $meta);
            }
            Log::warning('mevonpay.ledger.outbound_failed', [
                'flow_type' => $flowType,
                'reference' => $ref,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
    * @param  array<string, mixed>  $meta
    */
    private function updateOutboundBucket(string $payoutReference, string $payoutBucket, array $meta): ?MevonPayLedgerEntry
    {
        $entry = MevonPayLedgerEntry::query()
            ->where('direction', MevonPayLedgerEntry::DIRECTION_OUTBOUND)
            ->where('payout_reference', $payoutReference)
            ->first();

        if (! $entry) {
            return null;
        }

        $chargeApiFee = in_array($payoutBucket, [
            MavonPayTransferService::BUCKET_SUCCESSFUL,
            MavonPayTransferService::BUCKET_PENDING,
        ], true);

        $breakdown = $this->fees->outboundBreakdown((float) $entry->gross_amount, $chargeApiFee);
        $mergedMeta = array_merge(is_array($entry->meta) ? $entry->meta : [], $meta);

        $entry->update([
            'payout_bucket' => $payoutBucket,
            'mevon_outbound_fee' => $breakdown['outbound_fee'],
            'net_mevon_impact' => $breakdown['net_mevon_impact'],
            'meta' => $mergedMeta !== [] ? $mergedMeta : null,
        ]);

        return $entry->fresh();
    }

    private function inboundExists(string $externalReference): bool
    {
        return MevonPayLedgerEntry::query()
            ->where('direction', MevonPayLedgerEntry::DIRECTION_INBOUND)
            ->where('external_reference', $externalReference)
            ->exists();
    }

    private function outboundExists(string $payoutReference): bool
    {
        return MevonPayLedgerEntry::query()
            ->where('direction', MevonPayLedgerEntry::DIRECTION_OUTBOUND)
            ->where('payout_reference', $payoutReference)
            ->exists();
    }

    private function normalizeReference(?string $reference): ?string
    {
        $ref = trim((string) $reference);

        return $ref !== '' ? $ref : null;
    }

    private function isDuplicateKey(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'duplicate') || str_contains($msg, 'unique');
    }

    public static function sourcePayment(?Payment $payment): ?Payment
    {
        return $payment;
    }

    public static function sourceWalletTxn(?WhatsappWalletTransaction $txn): ?WhatsappWalletTransaction
    {
        return $txn;
    }

    public static function sourceWithdrawal(?WithdrawalRequest $withdrawal): ?WithdrawalRequest
    {
        return $withdrawal;
    }
}
