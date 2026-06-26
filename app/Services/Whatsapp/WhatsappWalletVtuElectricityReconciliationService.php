<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\VtuNg\VtuNgApiClient;
use App\Services\VtuNg\VtuNgElectricityOrderParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Poll VTU.ng for pending electricity orders, deliver tokens via WhatsApp, and update receipts.
 */
class WhatsappWalletVtuElectricityReconciliationService
{
    public function __construct(
        private VtuNgApiClient $vtuClient,
        private EvolutionWhatsAppClient $whatsappClient,
    ) {}

    /**
     * @return array{checked: int, completed: int, failed: int, skipped: int, notified: int}
     */
    public function reconcilePendingBatch(): array
    {
        if (! $this->isAvailable()) {
            return ['checked' => 0, 'completed' => 0, 'failed' => 0, 'skipped' => 0, 'notified' => 0];
        }

        $hours = max(1, (int) config('vtu.electricity_reconcile_hours', 48));
        $max = max(1, (int) config('vtu.electricity_reconcile_batch_size', 20));
        $minAgeMinutes = max(0, (int) config('vtu.electricity_reconcile_min_age_minutes', 2));

        $pending = WhatsappWalletTransaction::query()
            ->where('type', WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY)
            ->where('created_at', '>=', now()->subHours($hours))
            ->where('meta->vtu_pending', true)
            ->orderBy('created_at')
            ->limit($max)
            ->get();

        $stats = ['checked' => 0, 'completed' => 0, 'failed' => 0, 'skipped' => 0, 'notified' => 0];

        foreach ($pending as $txn) {
            if ($minAgeMinutes > 0 && $txn->created_at->greaterThan(now()->subMinutes($minAgeMinutes))) {
                $stats['skipped']++;

                continue;
            }

            if ($this->wasCheckedRecently($txn)) {
                $stats['skipped']++;

                continue;
            }

            $result = $this->reconcileTransaction($txn);
            if ($result['skipped'] ?? false) {
                $stats['skipped']++;
            } elseif ($result['checked'] ?? false) {
                $stats['checked']++;
            }
            if ($result['completed'] ?? false) {
                $stats['completed']++;
            }
            if ($result['failed'] ?? false) {
                $stats['failed']++;
            }
            if ($result['notified'] ?? false) {
                $stats['notified']++;
            }
        }

        return $stats;
    }

    /**
     * Lazy reconcile for one wallet (wallet submenu open).
     *
     * @return array{checked: int, completed: int, failed: int, skipped: int, notified: int}
     */
    public function reconcileWallet(WhatsappWallet $wallet): array
    {
        if (! $this->isAvailable()) {
            return ['checked' => 0, 'completed' => 0, 'failed' => 0, 'skipped' => 0, 'notified' => 0];
        }

        $hours = max(1, (int) config('vtu.electricity_reconcile_hours', 48));
        $max = max(1, (int) config('vtu.electricity_reconcile_max_per_wallet', 3));
        $minAgeMinutes = max(0, (int) config('vtu.electricity_reconcile_min_age_minutes', 2));

        $pending = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('type', WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY)
            ->where('created_at', '>=', now()->subHours($hours))
            ->where('meta->vtu_pending', true)
            ->orderBy('created_at')
            ->limit($max)
            ->get();

        $stats = ['checked' => 0, 'completed' => 0, 'failed' => 0, 'skipped' => 0, 'notified' => 0];

        foreach ($pending as $txn) {
            if ($minAgeMinutes > 0 && $txn->created_at->greaterThan(now()->subMinutes($minAgeMinutes))) {
                $stats['skipped']++;

                continue;
            }

            if ($this->wasCheckedRecently($txn)) {
                $stats['skipped']++;

                continue;
            }

            $result = $this->reconcileTransaction($txn);
            if ($result['skipped'] ?? false) {
                $stats['skipped']++;
            } elseif ($result['checked'] ?? false) {
                $stats['checked']++;
            }
            if ($result['completed'] ?? false) {
                $stats['completed']++;
            }
            if ($result['failed'] ?? false) {
                $stats['failed']++;
            }
            if ($result['notified'] ?? false) {
                $stats['notified']++;
            }
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcileTransaction(WhatsappWalletTransaction $transaction, bool $forceAdminCheck = false): array
    {
        if (! $this->isAvailable()) {
            return ['available' => false, 'message' => 'VTU.ng is not configured.', 'skipped' => true];
        }

        if ($transaction->type !== WhatsappWalletTransaction::TYPE_VTU_ELECTRICITY) {
            return ['available' => false, 'message' => 'Not an electricity transaction.', 'skipped' => true];
        }

        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        if (! $forceAdminCheck && ! ($meta['vtu_pending'] ?? false)) {
            return ['available' => true, 'message' => 'Not pending.', 'skipped' => true];
        }

        if (! empty($meta['vtu_refunded'])) {
            return ['available' => true, 'message' => 'Already refunded.', 'skipped' => true];
        }

        $requestId = $this->resolveRequestId($transaction);
        if ($requestId === null) {
            return ['available' => false, 'message' => 'Missing VTU request_id.', 'skipped' => true];
        }

        $api = $this->vtuClient->requeryOrder($requestId);
        if (! ($api['ok'] ?? false)) {
            return [
                'available' => true,
                'checked' => false,
                'skipped' => false,
                'message' => (string) ($api['message'] ?? 'VTU requery failed.'),
                'request_id' => $requestId,
                'requery_ok' => false,
            ];
        }

        $parsed = VtuNgElectricityOrderParser::parse($api);
        $result = $this->applyParsedStatus($transaction->fresh() ?? $transaction, $parsed, [
            'source' => $forceAdminCheck ? 'admin_requery' : 'requery',
            'requery_ok' => true,
            'requery_message' => (string) ($api['message'] ?? ''),
            'provider_payload' => $api['raw'] ?? $api['data'] ?? null,
        ]);

        return array_merge($result, [
            'available' => true,
            'request_id' => $requestId,
            'vtu_status' => (string) ($parsed['status'] ?? ''),
            'electricity_token' => $parsed['electricity_token'] ?? null,
            'requery_ok' => true,
        ]);
    }

    /**
     * Apply VTU.ng webhook payload to a matching electricity transaction.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyWebhookPayload(WhatsappWalletTransaction $transaction, array $payload): array
    {
        $parsed = VtuNgElectricityOrderParser::parseWebhook($payload);

        return $this->applyParsedStatus($transaction, $parsed, [
            'source' => 'webhook',
            'provider_payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function applyParsedStatus(WhatsappWalletTransaction $transaction, array $parsed, array $context): array
    {
        $status = (string) ($parsed['status'] ?? '');
        $token = $parsed['electricity_token'] ?? null;

        if (VtuNgElectricityOrderParser::isFailedStatus($status)) {
            return $this->markFailed($transaction, $status, $context);
        }

        if ($token !== null) {
            return $this->markCompleted($transaction, $parsed, $context);
        }

        if (VtuNgElectricityOrderParser::isProcessingStatus($status) || VtuNgElectricityOrderParser::shouldStayPending($parsed)) {
            return $this->markStillProcessing($transaction, $parsed, $context);
        }

        if (VtuNgElectricityOrderParser::isCompletedStatus($status)) {
            return $this->markStillProcessing($transaction, $parsed, $context);
        }

        return $this->markStillProcessing($transaction, $parsed, $context);
    }

    public function isAvailable(): bool
    {
        return config('vtu.enabled', false) && $this->vtuClient->isConfigured();
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function markCompleted(WhatsappWalletTransaction $transaction, array $parsed, array $context): array
    {
        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        $alreadyHadToken = trim((string) ($meta['electricity_token'] ?? '')) !== '';

        $meta = $this->mergeProviderFields($meta, $parsed, $context);
        $meta['vtu_pending'] = false;
        $meta['vtu_ok'] = true;
        $meta['vtu_status'] = (string) ($parsed['status'] ?? 'completed-api');
        $meta['vtu_completed_at'] = now()->toIso8601String();

        $transaction->update(['meta' => $meta]);
        $transaction = $transaction->fresh() ?? $transaction;

        $notified = false;
        if (! $alreadyHadToken && empty($meta['vtu_token_notified_at'])) {
            $notified = $this->notifyTokenDelivered($transaction);
            if ($notified) {
                $meta = is_array($transaction->meta) ? $transaction->meta : [];
                $meta['vtu_token_notified_at'] = now()->toIso8601String();
                $transaction->update(['meta' => $meta]);
            }
        }

        return [
            'available' => true,
            'checked' => true,
            'completed' => true,
            'notified' => $notified,
            'skipped' => false,
            'message' => 'Electricity order completed.',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function markFailed(WhatsappWalletTransaction $transaction, string $status, array $context): array
    {
        $result = app(WhatsappWalletVtuPurchaseService::class)
            ->refundElectricityTransaction($transaction, 'Provider status: '.$status);

        return [
            'available' => true,
            'checked' => true,
            'failed' => true,
            'skipped' => false,
            'refunded' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? 'Failed and refunded.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function markStillProcessing(WhatsappWalletTransaction $transaction, array $parsed, array $context): array
    {
        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        $meta = $this->mergeProviderFields($meta, $parsed, $context);
        $meta['vtu_pending'] = true;
        $status = (string) ($parsed['status'] ?? ($meta['vtu_status'] ?? 'processing-api'));
        $meta['vtu_status'] = $status !== '' ? $status : 'processing-api';
        $meta['vtu_last_requery_at'] = now()->toIso8601String();
        $meta['vtu_status_source'] = (string) ($context['source'] ?? 'unknown');

        $transaction->update(['meta' => $meta]);

        return [
            'available' => true,
            'checked' => true,
            'skipped' => false,
            'still_pending' => true,
            'message' => 'Order still processing.',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function mergeProviderFields(array $meta, array $parsed, array $context): array
    {
        if (($parsed['request_id'] ?? null) !== null) {
            $meta['vtu_request_id'] = $parsed['request_id'];
            $meta['vtu_provider_reference'] = $parsed['request_id'];
        }
        if (($parsed['order_id'] ?? null) !== null) {
            $meta['vtu_order_id'] = $parsed['order_id'];
        }
        if (($parsed['electricity_token'] ?? null) !== null) {
            $meta['electricity_token'] = $parsed['electricity_token'];
        }
        if (($parsed['units'] ?? null) !== null) {
            $meta['electricity_units'] = $parsed['units'];
        }
        if (($parsed['customer_name'] ?? null) !== null && empty($meta['customer_name'])) {
            $meta['customer_name'] = $parsed['customer_name'];
        }
        if (($parsed['meter_number'] ?? null) !== null && empty($meta['meter_number'])) {
            $meta['meter_number'] = $parsed['meter_number'];
        }

        $payload = $context['provider_payload'] ?? null;
        if ($payload !== null) {
            $meta['vtu_last_provider_payload'] = is_array($payload)
                ? array_intersect_key($payload, array_flip(['status', 'request_id', 'order_id', 'message', 'meta_data']))
                : $payload;
        }

        return $meta;
    }

    private function notifyTokenDelivered(WhatsappWalletTransaction $transaction): bool
    {
        $wallet = WhatsappWallet::query()->find($transaction->whatsapp_wallet_id);
        if (! $wallet) {
            return false;
        }

        $phone = trim((string) $wallet->phone_e164);
        if ($phone === '') {
            return false;
        }

        $instance = (string) (WhatsappSession::query()
            ->where('phone_e164', $phone)
            ->value('evolution_instance') ?? '');
        if ($instance === '') {
            $instance = (string) config('whatsapp.evolution.instance', '');
        }
        if ($instance === '') {
            Log::debug('whatsapp.vtu.electricity_token_notify: no evolution instance', [
                'transaction_id' => $transaction->id,
                'wallet_id' => $wallet->id,
            ]);

            return false;
        }

        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        $token = trim((string) ($meta['electricity_token'] ?? ''));
        if ($token === '') {
            return false;
        }

        $meter = trim((string) ($meta['meter_number'] ?? ''));
        $disco = trim((string) ($meta['service_id'] ?? ''));
        $units = trim((string) ($meta['electricity_units'] ?? ''));
        $amount = number_format(abs((float) $transaction->amount), 0);
        $customer = trim((string) ($meta['customer_name'] ?? ''));

        $lines = ["*Electricity token* ⚡\n"];
        if ($customer !== '') {
            $lines[] = "Customer: *{$customer}*";
        }
        if ($meter !== '') {
            $lines[] = "Meter: *{$meter}*";
        }
        if ($disco !== '') {
            $lines[] = "Disco: *{$disco}*";
        }
        $lines[] = "Token: *{$token}*";
        if ($units !== '') {
            $lines[] = "Units: *{$units}*";
        }
        $lines[] = "Amount: *₦{$amount}*";
        $lines[] = "\nLoad this on your meter. Keep this message for your records.";

        $sent = $this->whatsappClient->sendText($instance, $phone, implode("\n", $lines));
        if (! $sent) {
            Log::warning('whatsapp.vtu.electricity_token_notify_failed', [
                'transaction_id' => $transaction->id,
                'wallet_id' => $wallet->id,
            ]);
        }

        return $sent;
    }

    private function resolveRequestId(WhatsappWalletTransaction $transaction): ?string
    {
        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        foreach (['vtu_request_id', 'vtu_provider_reference'] as $key) {
            $id = trim((string) ($meta[$key] ?? ''));
            if ($id !== '') {
                return $id;
            }
        }

        return null;
    }

    private function wasCheckedRecently(WhatsappWalletTransaction $transaction): bool
    {
        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        $last = $meta['vtu_last_requery_at'] ?? null;
        if (! is_string($last) || $last === '') {
            return false;
        }

        try {
            $checkedAt = Carbon::parse($last);
        } catch (\Throwable) {
            return false;
        }

        $minutes = max(1, (int) config('vtu.electricity_reconcile_min_interval_minutes', 3));

        return $checkedAt->greaterThan(now()->subMinutes($minutes));
    }
}
