<?php

namespace App\Services\MevonPay;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Persists raw MevonPay funding.success webhook payloads on {@see Payment} rows (email_data JSON).
 */
final class MevonPayInboundWebhookRecorder
{
    private const HISTORY_LIMIT = 20;

    /**
     * @return array{sender: string, bank_name: string}
     */
    public static function metaFromPayload(array $payload): array
    {
        return [
            'sender' => (string) data_get($payload, 'data.sender', ''),
            'bank_name' => (string) data_get($payload, 'data.bank_name', ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildEntry(array $payload, string $handlerStatus, ?Request $request = null): array
    {
        $entry = [
            'received_at' => now()->toIso8601String(),
            'handler_status' => $handlerStatus,
            'event' => (string) data_get($payload, 'event', ''),
            'payload' => $payload,
        ];

        if ($request !== null) {
            $entry['request'] = [
                'ip' => (string) $request->ip(),
                'method' => (string) $request->method(),
                'path' => (string) $request->path(),
                'user_agent' => (string) $request->userAgent(),
            ];
        }

        return $entry;
    }

    /**
     * Merge latest webhook + history onto a payment (does not run through email sanitization).
     */
    public static function attach(Payment $payment, array $payload, string $handlerStatus, ?Request $request = null): void
    {
        $payment->refresh();
        $entry = self::buildEntry($payload, $handlerStatus, $request);
        $existing = is_array($payment->email_data) ? $payment->email_data : [];
        $history = $existing['mevonpay_inbound_webhooks'] ?? [];
        if (! is_array($history)) {
            $history = [];
        }
        $history[] = $entry;
        if (count($history) > self::HISTORY_LIMIT) {
            $history = array_slice($history, -1 * self::HISTORY_LIMIT);
        }
        $existing['mevonpay_inbound_webhook'] = $entry;
        $existing['mevonpay_inbound_webhooks'] = $history;
        $payment->update(['email_data' => $existing]);
    }

    /**
     * For Payment::create / email_data merges before the row exists.
     *
     * @return array<string, mixed>
     */
    public static function emailDataExtras(array $payload, string $handlerStatus, ?Request $request = null): array
    {
        $entry = self::buildEntry($payload, $handlerStatus, $request);

        return [
            'mevonpay_inbound_webhook' => $entry,
            'mevonpay_inbound_webhooks' => [$entry],
        ];
    }
}
