<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\VtuNg\VtuNgApiClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsappWalletVtuPurchaseService
{
    public function __construct(
        private VtuNgApiClient $vtu,
    ) {}

    /**
     * @return array{ok: bool, message: string, balance_after?: float}
     */
    public function purchaseAirtime(
        WhatsappWallet $wallet,
        string $networkId,
        string $recipientE164,
        float $amount
    ): array {
        $phone11 = PhoneNormalizer::e164DigitsToNgLocal11($recipientE164);
        if ($phone11 === null) {
            return ['ok' => false, 'message' => 'Invalid Nigerian phone number.'];
        }

        $ref = 'VTU-AIR-'.strtoupper(Str::random(14));
        $debited = $this->debitWallet($wallet, $amount, WhatsappWalletTransaction::TYPE_VTU_AIRTIME, $ref, [
            'vtu_kind' => 'airtime',
            'network_id' => $networkId,
            'recipient_local' => $phone11,
        ]);
        if (! $debited['ok']) {
            return $debited;
        }

        $api = $this->vtu->purchaseAirtime($networkId, $phone11, $amount);
        if (! $api['ok']) {
            $this->refundDebit($wallet->id, $ref, $amount, (string) ($api['message'] ?? 'Provider error'));

            return ['ok' => false, 'message' => (string) ($api['message'] ?? 'Purchase failed.')];
        }

        $this->finalizeTxnMeta($ref, [
            'vtu_ok' => true,
            'vtu_status' => 'success',
            'vtu_message' => $api['message'] ?? null,
            'vtu_data' => $api['data'] ?? null,
            'vtu_provider_reference' => $this->extractProviderReference($api),
        ]);
        $w = $wallet->fresh();

        return [
            'ok' => true,
            'message' => (string) ($api['message'] ?? 'Airtime sent.'),
            'balance_after' => $w ? (float) $w->balance : null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, balance_after?: float}
     */
    public function purchaseData(
        WhatsappWallet $wallet,
        string $networkId,
        string $recipientE164,
        int $variationId,
        float $expectedPrice
    ): array {
        $phone11 = PhoneNormalizer::e164DigitsToNgLocal11($recipientE164);
        if ($phone11 === null) {
            return ['ok' => false, 'message' => 'Invalid Nigerian phone number.'];
        }

        $amount = round($expectedPrice, 2);
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Invalid plan price.'];
        }

        $ref = 'VTU-DAT-'.strtoupper(Str::random(14));
        $debited = $this->debitWallet($wallet, $amount, WhatsappWalletTransaction::TYPE_VTU_DATA, $ref, [
            'vtu_kind' => 'data',
            'network_id' => $networkId,
            'variation_id' => $variationId,
            'recipient_local' => $phone11,
        ]);
        if (! $debited['ok']) {
            return $debited;
        }

        $api = $this->vtu->purchaseData($networkId, $phone11, $variationId);
        if (! $api['ok']) {
            $this->refundDebit($wallet->id, $ref, $amount, (string) ($api['message'] ?? 'Provider error'));

            return ['ok' => false, 'message' => (string) ($api['message'] ?? 'Purchase failed.')];
        }

        $this->finalizeTxnMeta($ref, [
            'vtu_ok' => true,
            'vtu_status' => 'success',
            'vtu_message' => $api['message'] ?? null,
            'vtu_data' => $api['data'] ?? null,
            'vtu_provider_reference' => $this->extractProviderReference($api),
        ]);
        $w = $wallet->fresh();

        return [
            'ok' => true,
            'message' => (string) ($api['message'] ?? 'Data bundle purchased.'),
            'balance_after' => $w ? (float) $w->balance : null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, balance_after?: float}
     */
    public function purchaseElectricity(
        WhatsappWallet $wallet,
        string $serviceId,
        string $meterNumber,
        string $variationId,
        string $payerPhoneE164,
        float $amount,
        ?string $customerName
    ): array {
        $phone11 = PhoneNormalizer::e164DigitsToNgLocal11($payerPhoneE164);
        if ($phone11 === null) {
            return ['ok' => false, 'message' => 'Invalid payer phone on wallet.'];
        }

        $amount = round($amount, 2);
        $ref = 'VTU-ELE-'.strtoupper(Str::random(14));
        $debited = $this->debitWallet($wallet, $amount, WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY, $ref, [
            'vtu_kind' => 'electricity',
            'service_id' => $serviceId,
            'meter_number' => $meterNumber,
            'variation_id' => $variationId,
            'customer_name' => $customerName,
        ]);
        if (! $debited['ok']) {
            return $debited;
        }

        $api = $this->vtu->purchaseElectricity($serviceId, $meterNumber, $phone11, $amount, $variationId);
        if (! $api['ok']) {
            $this->refundDebit($wallet->id, $ref, $amount, (string) ($api['message'] ?? 'Provider error'));

            return ['ok' => false, 'message' => (string) ($api['message'] ?? 'Purchase failed.')];
        }

        $this->finalizeTxnMeta($ref, [
            'vtu_ok' => true,
            'vtu_status' => 'success',
            'vtu_message' => $api['message'] ?? null,
            'vtu_data' => $api['data'] ?? null,
            'vtu_provider_reference' => $this->extractProviderReference($api),
        ]);
        $w = $wallet->fresh();

        return [
            'ok' => true,
            'message' => (string) ($api['message'] ?? 'Electricity payment submitted.'),
            'balance_after' => $w ? (float) $w->balance : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metaBase
     * @return array{ok: bool, message?: string}
     */
    private function debitWallet(
        WhatsappWallet $wallet,
        float $amount,
        string $type,
        string $externalRef,
        array $metaBase
    ): array {
        try {
            DB::transaction(function () use ($wallet, $amount, $type, $externalRef, $metaBase) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
                if (! $w) {
                    throw new \RuntimeException('wallet_missing');
                }
                $w->resetDailyTransferIfNeeded();
                $check = $w->canDebit($amount);
                if (! $w->hasPin() || ! $check['ok']) {
                    throw new \RuntimeException('cannot_debit');
                }
                $newBal = round((float) $w->balance - $amount, 2);
                $w->balance = $newBal;
                $w->daily_transfer_total = round((float) $w->daily_transfer_total + $amount, 2);
                $w->daily_transfer_for_date = now()->toDateString();
                $w->save();

                WhatsappWalletTransaction::query()->create([
                    'whatsapp_wallet_id' => $w->id,
                    'sender_name' => $w->normalizedSenderName(),
                    'type' => $type,
                    'amount' => $amount,
                    'balance_after' => $newBal,
                    'external_reference' => $externalRef,
                    'meta' => array_merge($metaBase, [
                        'channel' => 'whatsapp_vtu',
                        'vtu_pending' => true,
                    ]),
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('whatsapp.wallet.vtu_debit_failed', ['error' => $e->getMessage(), 'wallet_id' => $wallet->id]);

            return ['ok' => false, 'message' => 'Could not debit wallet. Check balance and Tier 1 daily limits.'];
        }

        return ['ok' => true];
    }

    private function refundDebit(int $walletId, string $externalRef, float $amount, string $reason): void
    {
        try {
            DB::transaction(function () use ($walletId, $externalRef, $amount, $reason) {
                $w = WhatsappWallet::query()->lockForUpdate()->find($walletId);
                $txn = WhatsappWalletTransaction::query()
                    ->where('external_reference', $externalRef)
                    ->where('whatsapp_wallet_id', $walletId)
                    ->first();

                if (! $txn) {
                    return;
                }

                $meta = is_array($txn->meta) ? $txn->meta : [];
                $alreadyRefunded = (bool) ($meta['vtu_refunded'] ?? false);
                if ($alreadyRefunded) {
                    return;
                }

                if ($w) {
                    $w->balance = round((float) $w->balance + $amount, 2);
                    $w->daily_transfer_total = max(0, round((float) $w->daily_transfer_total - $amount, 2));
                    $w->save();

                    WhatsappWalletTransaction::query()->create([
                        'whatsapp_wallet_id' => $w->id,
                        'sender_name' => $w->normalizedSenderName(),
                        'type' => WhatsappWalletTransaction::TYPE_ADJUSTMENT,
                        'amount' => $amount,
                        'balance_after' => (float) $w->balance,
                        'external_reference' => $externalRef.'-REFUND',
                        'meta' => [
                            'channel' => 'whatsapp_vtu',
                            'adjustment_kind' => 'vtu_refund',
                            'refunded_external_reference' => $externalRef,
                            'reason' => $reason,
                        ],
                    ]);
                }

                $meta['vtu_pending'] = false;
                $meta['vtu_refunded'] = true;
                $meta['vtu_refund_reason'] = $reason;
                $meta['vtu_refunded_at'] = now()->toIso8601String();
                $txn->update(['meta' => $meta]);
            });
        } catch (\Throwable $e) {
            Log::error('whatsapp.wallet.vtu_refund_failed', [
                'error' => $e->getMessage(),
                'wallet_id' => $walletId,
                'ref' => $externalRef,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function finalizeTxnMeta(string $externalRef, array $extra): void
    {
        $txn = WhatsappWalletTransaction::query()->where('external_reference', $externalRef)->first();
        if (! $txn) {
            return;
        }
        $meta = is_array($txn->meta) ? $txn->meta : [];
        $meta['vtu_pending'] = false;
        $txn->update(['meta' => array_merge($meta, $extra)]);
    }

    /**
     * Handle asynchronous provider status updates (e.g. reversed/refunded).
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string}
     */
    public function processProviderStatusWebhook(array $payload): array
    {
        $status = strtolower(trim((string) (
            Arr::get($payload, 'status')
            ?? Arr::get($payload, 'data.status')
            ?? Arr::get($payload, 'event')
            ?? Arr::get($payload, 'code')
            ?? ''
        )));

        $refundStatuses = array_map(
            static fn ($s) => strtolower(trim((string) $s)),
            (array) config('vtu.refund_statuses', ['refund', 'refunded', 'reversed', 'reversal', 'failed'])
        );

        $references = array_filter(array_unique(array_map(
            static fn ($v) => trim((string) $v),
            [
                Arr::get($payload, 'reference'),
                Arr::get($payload, 'request_id'),
                Arr::get($payload, 'transaction_id'),
                Arr::get($payload, 'id'),
                Arr::get($payload, 'data.reference'),
                Arr::get($payload, 'data.request_id'),
                Arr::get($payload, 'data.transaction_id'),
                Arr::get($payload, 'data.id'),
            ]
        )));

        if ($references === []) {
            return ['ok' => false, 'message' => 'No reference in webhook payload.'];
        }

        $txn = $this->findVtuDebitTransactionByReferences($references);
        if (! $txn) {
            return ['ok' => false, 'message' => 'No matching VTU transaction found.'];
        }

        if (in_array($status, $refundStatuses, true)) {
            $amount = (float) $txn->amount;
            $this->refundDebit((int) $txn->whatsapp_wallet_id, (string) $txn->external_reference, $amount, 'Provider status: '.$status);

            return ['ok' => true, 'message' => 'Wallet refunded for VTU reversal.'];
        }

        $meta = is_array($txn->meta) ? $txn->meta : [];
        $meta['vtu_status'] = $status !== '' ? $status : 'unknown';
        $meta['vtu_status_payload'] = Arr::only($payload, ['status', 'event', 'code', 'message', 'data']);
        $meta['vtu_last_status_at'] = now()->toIso8601String();
        $meta['vtu_pending'] = false;
        $txn->update(['meta' => $meta]);

        return ['ok' => true, 'message' => 'VTU status updated.'];
    }

    /**
     * @param  array<int, string>  $references
     */
    private function findVtuDebitTransactionByReferences(array $references): ?WhatsappWalletTransaction
    {
        $txn = WhatsappWalletTransaction::query()
            ->whereIn('type', [
                WhatsappWalletTransaction::TYPE_VTU_AIRTIME,
                WhatsappWalletTransaction::TYPE_VTU_DATA,
                WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY,
            ])
            ->whereIn('external_reference', $references)
            ->latest('id')
            ->first();

        if ($txn) {
            return $txn;
        }

        // Fallback for provider references stored in metadata.
        $candidates = WhatsappWalletTransaction::query()
            ->whereIn('type', [
                WhatsappWalletTransaction::TYPE_VTU_AIRTIME,
                WhatsappWalletTransaction::TYPE_VTU_DATA,
                WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY,
            ])
            ->latest('id')
            ->limit(300)
            ->get();

        foreach ($candidates as $candidate) {
            $meta = is_array($candidate->meta) ? $candidate->meta : [];
            $providerRef = trim((string) ($meta['vtu_provider_reference'] ?? ''));
            if ($providerRef !== '' && in_array($providerRef, $references, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $api
     */
    private function extractProviderReference(array $api): ?string
    {
        $ref = (string) (
            Arr::get($api, 'data.reference')
            ?? Arr::get($api, 'data.request_id')
            ?? Arr::get($api, 'data.transaction_id')
            ?? Arr::get($api, 'data.id')
            ?? ''
        );

        $ref = trim($ref);

        return $ref !== '' ? $ref : null;
    }
}
