<?php

namespace App\Services\LiveSync;

use App\Jobs\LiveSyncPushJob;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Renter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Builds whitelist payloads and pushes (or queues) them to the Contabo receiver.
 */
final class LiveSyncOutboundService
{
    private static bool $suppress = false;

    public function __construct(
        private LiveSyncTransmitterClient $client,
    ) {}

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutOutbound(callable $callback): mixed
    {
        $previous = self::$suppress;
        self::$suppress = true;
        try {
            return $callback();
        } finally {
            self::$suppress = $previous;
        }
    }

    public static function isSuppressed(): bool
    {
        return self::$suppress;
    }

    public function shouldTransmit(): bool
    {
        return ! self::$suppress && $this->client->isConfigured();
    }

    public function pushPayment(Payment $payment, string $operation = 'upsert'): void
    {
        if (! $this->shouldTransmit()) {
            return;
        }

        $data = $this->paymentPayload($payment);
        if (($data['transaction_id'] ?? '') === '') {
            return;
        }

        $this->dispatch('payment', $operation, $data);
    }

    public function pushBusiness(Business $business, string $operation = 'upsert'): void
    {
        if (! $this->shouldTransmit()) {
            return;
        }

        $data = $this->businessPayload($business);
        if (($data['business_id'] ?? '') === '' && ($data['email'] ?? '') === '') {
            return;
        }

        $this->dispatch('business', $operation, $data);
    }

    public function pushRenter(Renter $renter, string $operation = 'upsert'): void
    {
        if (! $this->shouldTransmit()) {
            return;
        }

        $data = $this->renterPayload($renter);
        if (($data['email'] ?? '') === '') {
            return;
        }

        $this->dispatch('renter', $operation, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message: string, event_id: string, status?: int, body?: mixed}
     */
    public function pushNow(string $entity, string $operation, array $data): array
    {
        $payload = [
            'event_id' => (string) Str::uuid(),
            'source' => (string) config('services.live_sync.source_name', 'namecheap-live'),
            'entity' => $entity,
            'operation' => $operation,
            'sent_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        return $this->client->send($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentPayload(Payment $payment): array
    {
        $dates = [
            'matched_at', 'expires_at', 'checkout_pay_code_expires_at',
            'webhook_sent_at', 'developer_program_partner_share_credited_at',
        ];

        $row = [
            'transaction_id' => (string) $payment->transaction_id,
            'amount' => $payment->amount !== null ? (float) $payment->amount : null,
            'payer_name' => $payment->payer_name,
            'bank' => $payment->bank,
            'webhook_url' => $payment->webhook_url,
            'account_number' => $payment->account_number,
            'payer_account_number' => $payment->payer_account_number,
            'business_id' => $payment->business_id,
            'user_id' => $payment->user_id,
            'renter_id' => $payment->renter_id,
            'business_website_id' => $payment->business_website_id,
            'rental_id' => $payment->rental_id,
            'status' => $payment->status,
            'payment_source' => $payment->payment_source,
            'payment_method_used' => $payment->payment_method_used,
            'external_reference' => $payment->external_reference,
            'checkout_pay_code' => $payment->checkout_pay_code,
            'received_amount' => $payment->received_amount !== null ? (float) $payment->received_amount : null,
            'is_mismatch' => (bool) $payment->is_mismatch,
            'mismatch_reason' => $payment->mismatch_reason,
            'charge_percentage' => $payment->charge_percentage !== null ? (float) $payment->charge_percentage : null,
            'charge_fixed' => $payment->charge_fixed !== null ? (float) $payment->charge_fixed : null,
            'total_charges' => $payment->total_charges !== null ? (float) $payment->total_charges : null,
            'business_receives' => $payment->business_receives !== null ? (float) $payment->business_receives : null,
            'charges_paid_by_customer' => $payment->charges_paid_by_customer,
            'webhook_status' => $payment->webhook_status,
            'webhook_attempts' => $payment->webhook_attempts,
            'developer_program_partner_business_id' => $payment->developer_program_partner_business_id,
            'developer_program_partner_share_amount' => $payment->developer_program_partner_share_amount !== null
                ? (float) $payment->developer_program_partner_share_amount
                : null,
        ];

        foreach ($dates as $col) {
            $val = $payment->{$col} ?? null;
            $row[$col] = $val ? optional($val)->toIso8601String() : null;
        }

        return array_filter($row, static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public function businessPayload(Business $business): array
    {
        return array_filter([
            'business_id' => $business->business_id,
            'name' => $business->name,
            'email' => $business->email,
            'phone' => $business->phone,
            'address' => $business->address,
            'website' => $business->website,
            'webhook_url' => $business->webhook_url,
            'is_active' => $business->is_active,
            'website_approved' => $business->website_approved,
            'timezone' => $business->timezone,
            'currency' => $business->currency,
            'charges_paid_by_customer' => $business->charges_paid_by_customer,
            'charge_percentage' => $business->charge_percentage !== null ? (float) $business->charge_percentage : null,
            'charge_fixed' => $business->charge_fixed !== null ? (float) $business->charge_fixed : null,
            'charge_exempt' => $business->charge_exempt,
            'balance' => $business->balance !== null ? (float) $business->balance : null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public function renterPayload(Renter $renter): array
    {
        return array_filter([
            'name' => $renter->name,
            'email' => $renter->email,
            'phone' => $renter->phone,
            'address' => $renter->address,
            'wallet_balance' => $renter->wallet_balance !== null ? (float) $renter->wallet_balance : null,
            'verified_account_number' => $renter->verified_account_number,
            'verified_account_name' => $renter->verified_account_name,
            'verified_bank_name' => $renter->verified_bank_name,
            'verified_bank_code' => $renter->verified_bank_code,
            'kyc_verified_at' => optional($renter->kyc_verified_at)?->toIso8601String(),
            'kyc_id_status' => $renter->kyc_id_status,
            'is_active' => $renter->is_active,
            'whatsapp_phone_e164' => $renter->whatsapp_phone_e164,
            'whatsapp_verified_at' => optional($renter->whatsapp_verified_at)?->toIso8601String(),
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function dispatch(string $entity, string $operation, array $data): void
    {
        $payload = [
            'event_id' => (string) Str::uuid(),
            'source' => (string) config('services.live_sync.source_name', 'namecheap-live'),
            'entity' => $entity,
            'operation' => $operation,
            'sent_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        if ((bool) config('services.live_sync.queue', true)) {
            $pending = LiveSyncPushJob::dispatch($payload);
            $connection = config('services.live_sync.queue_connection');
            if (is_string($connection) && $connection !== '') {
                $pending->onConnection($connection);
            }
            $queue = config('services.live_sync.queue_name');
            if (is_string($queue) && $queue !== '') {
                $pending->onQueue($queue);
            }

            return;
        }

        $result = $this->client->send($payload);
        if (! ($result['ok'] ?? false)) {
            Log::warning('live_sync.outbound_sync_failed', [
                'entity' => $entity,
                'operation' => $operation,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
