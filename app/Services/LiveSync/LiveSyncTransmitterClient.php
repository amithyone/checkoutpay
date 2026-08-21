<?php

namespace App\Services\LiveSync;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HMAC client that POSTs events to Contabo's POST /api/v1/sync/live receiver.
 */
final class LiveSyncTransmitterClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status?: int, body?: mixed, message: string, event_id: string}
     */
    public function send(array $payload): array
    {
        $eventId = (string) ($payload['event_id'] ?? '');
        if ($eventId === '') {
            $eventId = (string) Str::uuid();
            $payload['event_id'] = $eventId;
        }

        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Live sync transmitter is not configured (set LIVE_SYNC_TRANSMIT_ENABLED, RECEIVER_URL, KEY_ID, SECRET).',
                'event_id' => $eventId,
            ];
        }

        $url = (string) config('services.live_sync.receiver_url');
        $path = (string) config('services.live_sync.receiver_path', '/api/v1/sync/live');
        if ($path === '' || $path[0] !== '/') {
            $path = '/'.ltrim($path, '/');
        }

        $payload['source'] = $payload['source'] ?? (string) config('services.live_sync.source_name', 'namecheap-live');
        $payload['sent_at'] = $payload['sent_at'] ?? now()->toIso8601String();

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return ['ok' => false, 'message' => 'Failed to encode sync payload.', 'event_id' => $eventId];
        }

        $timestamp = (string) time();
        $nonce = (string) Str::uuid();
        $bodyHash = hash('sha256', $body);
        $canonical = implode("\n", ['POST', $path, $timestamp, $nonce, $bodyHash]);
        $signature = hash_hmac('sha256', $canonical, (string) config('services.live_sync.secret'));

        try {
            $response = Http::timeout((int) config('services.live_sync.timeout_seconds', 15))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-LiveSync-Key' => (string) config('services.live_sync.key_id'),
                    'X-LiveSync-Timestamp' => $timestamp,
                    'X-LiveSync-Nonce' => $nonce,
                    'X-LiveSync-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            $ok = $response->successful() && (($response->json('success') ?? true) !== false);
            if (! $ok) {
                Log::warning('live_sync.transmit_failed', [
                    'event_id' => $eventId,
                    'entity' => $payload['entity'] ?? null,
                    'http_status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ]);
            }

            return [
                'ok' => $ok,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'message' => $ok
                    ? (string) ($response->json('message') ?? 'Sync accepted')
                    : (string) ($response->json('message') ?? 'Receiver rejected sync'),
                'event_id' => $eventId,
            ];
        } catch (\Throwable $e) {
            Log::warning('live_sync.transmit_exception', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'event_id' => $eventId,
            ];
        }
    }

    public function isConfigured(): bool
    {
        if (! (bool) config('services.live_sync.transmit_enabled', false)) {
            return false;
        }

        $url = trim((string) config('services.live_sync.receiver_url', ''));
        $secret = (string) config('services.live_sync.secret', '');
        $keyId = (string) config('services.live_sync.key_id', '');

        return $url !== '' && $secret !== '' && $keyId !== '';
    }
}
