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

    /**
     * Direct Mevon NGN wallet credit (e.g. FX USD→NGN) — impact = +gross, no inbound VA fee.
     *
     * @param  array<string, mixed>  $meta
     */
    public function recordNgnCredit(
        string $flowType,
        float $grossAmount,
        string $reference,
        string $creditApi,
        ?Model $source = null,
        array $meta = [],
        ?Carbon $occurredAt = null,
    ): ?MevonPayLedgerEntry {
        $grossAmount = round($grossAmount, 2);
        if ($grossAmount <= 0) {
            return null;
        }

        $ref = $this->normalizeReference($reference);
        if ($ref === null || $this->inboundExists($ref)) {
            return null;
        }

        try {
            return MevonPayLedgerEntry::query()->create([
                'direction' => MevonPayLedgerEntry::DIRECTION_INBOUND,
                'flow_type' => $flowType,
                'gross_amount' => $grossAmount,
                'mevon_inbound_fee' => 0,
                'mevon_outbound_fee' => null,
                'net_mevon_impact' => $grossAmount,
                'external_reference' => $ref,
                'payout_reference' => null,
                'account_number' => (string) config('services.mevonpay.debit_account_number', '') ?: null,
                'source_type' => $source !== null ? $source->getMorphClass() : null,
                'source_id' => $source?->getKey(),
                'payout_api' => $creditApi,
                'payout_bucket' => null,
                'meta' => array_merge(['ngn_credit' => true], $meta),
                'occurred_at' => $occurredAt ?? now(),
            ]);
        } catch (\Throwable $e) {
            if ($this->isDuplicateKey($e)) {
                return null;
            }
            Log::warning('mevonpay.ledger.credit_failed', [
                'flow_type' => $flowType,
                'reference' => $ref,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Direct Mevon NGN wallet drain (FX / VTU / identity fees) — impact = −gross, no createtransfer API fee.
     *
     * @param  array<string, mixed>  $meta
     */
    public function recordNgnDrain(
        string $flowType,
        float $grossAmount,
        string $reference,
        string $drainApi,
        ?Model $source = null,
        array $meta = [],
        ?Carbon $occurredAt = null,
    ): ?MevonPayLedgerEntry {
        $grossAmount = round($grossAmount, 2);
        if ($grossAmount <= 0) {
            return null;
        }

        $ref = trim($reference);
        if ($ref === '') {
            return null;
        }

        if ($this->outboundExists($ref)) {
            return null;
        }

        $impact = round(-1 * $grossAmount, 2);

        try {
            return MevonPayLedgerEntry::query()->create([
                'direction' => MevonPayLedgerEntry::DIRECTION_OUTBOUND,
                'flow_type' => $flowType,
                'gross_amount' => $grossAmount,
                'mevon_inbound_fee' => null,
                'mevon_outbound_fee' => 0,
                'net_mevon_impact' => $impact,
                'external_reference' => null,
                'payout_reference' => $ref,
                'account_number' => (string) config('services.mevonpay.debit_account_number', '') ?: null,
                'source_type' => $source !== null ? $source->getMorphClass() : null,
                'source_id' => $source?->getKey(),
                'payout_api' => $drainApi,
                'payout_bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
                'meta' => array_merge(['ngn_drain' => true], $meta),
                'occurred_at' => $occurredAt ?? now(),
            ]);
        } catch (\Throwable $e) {
            if ($this->isDuplicateKey($e)) {
                return null;
            }
            Log::warning('mevonpay.ledger.drain_failed', [
                'flow_type' => $flowType,
                'reference' => $ref,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Recompute fee + net_mevon_impact for an existing row (after formula changes).
     */
    public function recomputeEntryImpacts(MevonPayLedgerEntry $entry): MevonPayLedgerEntry
    {
        $gross = (float) $entry->gross_amount;

        if ($entry->direction === MevonPayLedgerEntry::DIRECTION_INBOUND) {
            $meta = is_array($entry->meta) ? $entry->meta : [];
            if (! empty($meta['ngn_credit'])) {
                $entry->update([
                    'mevon_inbound_fee' => 0,
                    'mevon_outbound_fee' => null,
                    'net_mevon_impact' => round((float) $entry->gross_amount, 2),
                ]);

                return $entry->fresh() ?? $entry;
            }

            $breakdown = $this->fees->inboundBreakdown($gross);
            $entry->update([
                'mevon_inbound_fee' => $breakdown['inbound_fee'],
                'mevon_outbound_fee' => null,
                'net_mevon_impact' => $breakdown['net_mevon_impact'],
            ]);

            return $entry->fresh() ?? $entry;
        }

        $meta = is_array($entry->meta) ? $entry->meta : [];
        $isDrain = ! empty($meta['ngn_drain'])
            || in_array((string) $entry->payout_api, [
                MevonPayLedgerEntry::PAYOUT_API_EXCHANGE,
                MevonPayLedgerEntry::PAYOUT_API_VTU,
                MevonPayLedgerEntry::PAYOUT_API_IDENTITY,
            ], true);

        if ($isDrain) {
            $bucket = (string) ($entry->payout_bucket ?? '');
            $impact = in_array($bucket, [
                MavonPayTransferService::BUCKET_FAILED,
            ], true) ? 0.0 : round(-1 * $gross, 2);

            $entry->update([
                'mevon_inbound_fee' => null,
                'mevon_outbound_fee' => 0,
                'net_mevon_impact' => $impact,
            ]);

            return $entry->fresh() ?? $entry;
        }

        $chargeApiFee = in_array((string) $entry->payout_bucket, [
            MavonPayTransferService::BUCKET_SUCCESSFUL,
            MavonPayTransferService::BUCKET_PENDING,
        ], true);

        $breakdown = $this->fees->outboundBreakdown($gross, $chargeApiFee);
        $entry->update([
            'mevon_inbound_fee' => null,
            'mevon_outbound_fee' => $breakdown['outbound_fee'],
            'net_mevon_impact' => $breakdown['net_mevon_impact'],
        ]);

        return $entry->fresh() ?? $entry;
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
