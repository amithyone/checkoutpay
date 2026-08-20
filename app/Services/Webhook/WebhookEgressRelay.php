<?php

namespace App\Services\Webhook;

use App\Support\SafeOutboundUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Contabo → Namecheap egress: deliver merchant webhooks from check-outpay.com IP
 * while Contabo remains the payment brain (until DNS cutover).
 */
class WebhookEgressRelay
{
    public static function clientEnabled(): bool
    {
        return (bool) config('checkout.webhook_egress.relay_client_enabled', false)
            && (string) config('checkout.webhook_egress.relay_url', '') !== ''
            && (string) config('checkout.webhook_egress.relay_secret', '') !== '';
    }

    public static function receiverEnabled(): bool
    {
        return (bool) config('checkout.webhook_egress.relay_receiver_enabled', false)
            && (string) config('checkout.webhook_egress.relay_secret', '') !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, status: ?int, response_body: ?string, error?: string, via: string}
     */
    public static function deliver(string $webhookUrl, array $payload): array
    {
        if (self::clientEnabled()) {
            return self::deliverViaRelay($webhookUrl, $payload);
        }

        return self::deliverDirect($webhookUrl, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, status: ?int, response_body: ?string, error?: string, via: string}
     */
    public static function deliverDirect(string $webhookUrl, array $payload): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(self::merchantHeaders())
                ->retry(2, 100)
                ->post($webhookUrl, $payload);

            return self::normalizeHttpResult($response->status(), $response->reason(), $response->body(), 'direct');
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => null,
                'response_body' => null,
                'error' => $e->getMessage(),
                'via' => 'direct',
            ];
        }
    }

    /**
     * Namecheap receiver: forward to merchant after SSRF checks.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, status: ?int, response_body: ?string, error?: string, via: string}
     */
    public static function forwardAsReceiver(string $webhookUrl, array $payload): array
    {
        $reason = SafeOutboundUrl::rejectionReason($webhookUrl);
        if ($reason !== null) {
            return [
                'success' => false,
                'status' => null,
                'response_body' => null,
                'error' => $reason,
                'via' => 'relay-receiver',
            ];
        }

        $result = self::deliverDirect($webhookUrl, $payload);
        $result['via'] = 'relay-receiver';

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, status: ?int, response_body: ?string, error?: string, via: string}
     */
    private static function deliverViaRelay(string $webhookUrl, array $payload): array
    {
        $relayUrl = (string) config('checkout.webhook_egress.relay_url');
        $secret = (string) config('checkout.webhook_egress.relay_secret');
        $timestamp = (string) time();
        $nonce = (string) Str::uuid();
        $body = json_encode([
            'target_url' => $webhookUrl,
            'payload' => $payload,
        ], JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return [
                'success' => false,
                'status' => null,
                'response_body' => null,
                'error' => 'Failed to encode relay body',
                'via' => 'relay-client',
            ];
        }

        $signature = self::sign($timestamp, $nonce, $body, $secret);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Egress-Timestamp' => $timestamp,
                    'X-Webhook-Egress-Nonce' => $nonce,
                    'X-Webhook-Egress-Signature' => $signature,
                    'User-Agent' => 'CheckoutPay-EgressRelay/1.0',
                ])
                ->withBody($body, 'application/json')
                ->post($relayUrl);

            $json = $response->json();
            if (is_array($json) && array_key_exists('success', $json)) {
                return [
                    'success' => (bool) $json['success'],
                    'status' => isset($json['status']) ? (int) $json['status'] : $response->status(),
                    'response_body' => isset($json['response_body']) ? (string) $json['response_body'] : null,
                    'error' => isset($json['error']) ? (string) $json['error'] : null,
                    'via' => 'relay-client',
                ];
            }

            return self::normalizeHttpResult($response->status(), $response->reason(), $response->body(), 'relay-client');
        } catch (\Throwable $e) {
            Log::warning('webhook_egress_relay_failed', ['error' => $e->getMessage()]);

            // Fail open to direct delivery so Contabo still attempts merchant webhook
            if ((bool) config('checkout.webhook_egress.fallback_direct', true)) {
                $direct = self::deliverDirect($webhookUrl, $payload);
                $direct['error'] = trim(($direct['error'] ?? '').' | relay_error: '.$e->getMessage());

                return $direct;
            }

            return [
                'success' => false,
                'status' => null,
                'response_body' => null,
                'error' => $e->getMessage(),
                'via' => 'relay-client',
            ];
        }
    }

    public static function sign(string $timestamp, string $nonce, string $rawBody, string $secret): string
    {
        $bodyHash = hash('sha256', $rawBody);
        $canonical = $timestamp."\n".$nonce."\n".$bodyHash;

        return hash_hmac('sha256', $canonical, $secret);
    }

    /**
     * @return array<string, string>
     */
    private static function merchantHeaders(): array
    {
        return [
            'User-Agent' => (string) config('checkout.webhook_egress.user_agent', 'CheckoutPay-Webhook/1.0 (+https://check-outpay.com)'),
            'X-Checkout-Source' => 'check-outpay.com',
        ];
    }

    /**
     * @return array{success: bool, status: ?int, response_body: ?string, error?: string, via: string}
     */
    private static function normalizeHttpResult(int $status, string $reason, string $rawBody, string $via): array
    {
        $bodyPreview = mb_strlen($rawBody) > 4000
            ? mb_substr($rawBody, 0, 4000).'…(truncated)'
            : $rawBody;

        if ($status >= 200 && $status < 300) {
            return [
                'success' => true,
                'status' => $status,
                'response_body' => $bodyPreview !== '' ? $bodyPreview : null,
                'via' => $via,
            ];
        }

        return [
            'success' => false,
            'status' => $status,
            'response_body' => $bodyPreview !== '' ? $bodyPreview : null,
            'error' => trim(sprintf('HTTP %d %s\nResponse body: %s', $status, $reason, $rawBody !== '' ? $rawBody : '(empty)')),
            'via' => $via,
        ];
    }
}
