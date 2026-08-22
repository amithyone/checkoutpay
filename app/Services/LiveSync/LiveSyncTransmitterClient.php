<?php

namespace App\Services\LiveSync;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HMAC client that POSTs events to Contabo's live-sync receiver.
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

        $payload['source'] = $payload['source'] ?? (string) config('services.live_sync.source_name', 'namecheap-live');
        $payload['sent_at'] = $payload['sent_at'] ?? now()->toIso8601String();

        $result = $this->signedPost(
            $this->receiverUrl(),
            $this->receiverPath(),
            $payload,
        );

        return [
            'ok' => $result['ok'],
            'status' => $result['status'] ?? null,
            'body' => $result['body'] ?? null,
            'message' => $result['message'],
            'event_id' => $eventId,
        ];
    }

    /**
     * Ask Contabo which keys are not present yet (avoids re-pushing the whole table).
     *
     * @param  list<string>  $keys
     * @return array{ok: bool, missing: list<string>, present: list<string>, message: string}
     */
    public function probeMissing(string $entity, array $keys): array
    {
        $keys = array_values(array_unique(array_filter(array_map(
            static fn ($k) => trim((string) $k),
            $keys,
        ), static fn ($k) => $k !== '')));

        if ($keys === []) {
            return ['ok' => true, 'missing' => [], 'present' => [], 'message' => 'No keys'];
        }

        $path = $this->receiverPath().'/probe';
        $url = rtrim($this->receiverUrl(), '/');
        if (str_ends_with($url, '/api/v1/sync/live')) {
            $url .= '/probe';
        } else {
            $url = rtrim((string) config('services.live_sync.receiver_url'), '/').'/probe';
            $path = '/api/v1/sync/live/probe';
        }

        $chunkSize = max(25, min(500, (int) config('live_sync.batch.probe_chunk', 50)));
        $probeTimeout = max(15, (int) config('live_sync.batch.probe_timeout_seconds', 60));
        $missing = [];
        $present = [];

        foreach (array_chunk($keys, $chunkSize) as $chunk) {
            $result = $this->signedPost($url, $path, [
                'entity' => $entity,
                'keys' => $chunk,
            ], $probeTimeout);

            if (! ($result['ok'] ?? false)) {
                $detail = (string) ($result['message'] ?? 'Probe failed');
                $status = $result['status'] ?? null;
                if (is_array($result['body'] ?? null) && isset($result['body']['message'])) {
                    $detail = (string) $result['body']['message'];
                }

                return [
                    'ok' => false,
                    'missing' => [],
                    'present' => [],
                    'message' => $status ? "HTTP {$status}: {$detail}" : $detail,
                ];
            }

            $data = is_array($result['body']['data'] ?? null) ? $result['body']['data'] : [];
            array_push($missing, ...array_map('strval', $data['missing'] ?? []));
            array_push($present, ...array_map('strval', $data['present'] ?? []));
        }

        return [
            'ok' => true,
            'missing' => array_values(array_unique($missing)),
            'present' => array_values(array_unique($present)),
            'message' => 'OK',
        ];
    }

    /**
     * Send many events in one signed POST (much faster than row-by-row).
     *
     * @param  list<array<string, mixed>>  $events
     * @return array{ok: bool, status?: int, body?: mixed, message: string, processed?: int, failed?: int}
     */
    public function sendBatch(array $events): array
    {
        $maxEvents = max(1, min(50, (int) config('live_sync.batch.max_events', 50)));
        $events = array_values($events);
        if ($events === []) {
            return ['ok' => true, 'message' => 'No events', 'processed' => 0, 'failed' => 0];
        }
        if (count($events) > $maxEvents) {
            return ['ok' => false, 'message' => "Batch exceeds max {$maxEvents} events"];
        }

        $normalized = [];
        foreach ($events as $event) {
            $eventId = (string) ($event['event_id'] ?? '');
            if ($eventId === '') {
                $eventId = (string) Str::uuid();
            }
            $normalized[] = array_merge($event, [
                'event_id' => $eventId,
                'source' => $event['source'] ?? (string) config('services.live_sync.source_name', 'namecheap-live'),
                'sent_at' => $event['sent_at'] ?? now()->toIso8601String(),
            ]);
        }

        $path = $this->receiverPath().'/batch';
        $url = rtrim($this->receiverUrl(), '/');
        if (str_ends_with($url, '/api/v1/sync/live')) {
            $url .= '/batch';
        } else {
            $url = rtrim((string) config('services.live_sync.receiver_url'), '/').'/batch';
            $path = '/api/v1/sync/live/batch';
        }

        $result = $this->signedPost($url, $path, ['events' => $normalized]);
        $data = is_array($result['body']['data'] ?? null) ? $result['body']['data'] : [];

        return [
            'ok' => $result['ok'] ?? false,
            'status' => $result['status'] ?? null,
            'body' => $result['body'] ?? null,
            'message' => (string) ($result['message'] ?? 'Batch failed'),
            'processed' => (int) ($data['processed'] ?? 0),
            'failed' => (int) ($data['failed'] ?? 0),
        ];
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status?: int, body?: mixed, message: string}
     */
    private function signedPost(string $url, string $path, array $payload, ?int $timeoutSeconds = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Live sync transmitter is not configured (set LIVE_SYNC_TRANSMIT_ENABLED, RECEIVER_URL, KEY_ID, SECRET).',
            ];
        }

        if ($path === '' || $path[0] !== '/') {
            $path = '/'.ltrim($path, '/');
        }

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return ['ok' => false, 'message' => 'Failed to encode sync payload.'];
        }

        $maxAttempts = 3;
        $timeout = $timeoutSeconds ?? max(3, (int) config('services.live_sync.timeout_seconds', 15));
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $auth = $this->signRequest($path, $body);

            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'X-LiveSync-Key' => (string) config('services.live_sync.key_id'),
                        'X-LiveSync-Timestamp' => $auth['timestamp'],
                        'X-LiveSync-Nonce' => $auth['nonce'],
                        'X-LiveSync-Signature' => $auth['signature'],
                    ])
                    ->withBody($body, 'application/json')
                    ->post($url);

                $retryable = in_array($response->status(), [409, 429], true);
                if ($retryable && $attempt < $maxAttempts) {
                    if ($response->status() === 429) {
                        $retryAfter = (int) ($response->header('Retry-After') ?: 5);
                        usleep(max(1, $retryAfter) * 1_000_000);
                    } else {
                        // 409 replay: prior attempt likely reached Contabo; fresh nonce and retry.
                        usleep(500_000);
                    }

                    continue;
                }

                $json = $response->json();
                $ok = $response->successful() && (($json['success'] ?? true) !== false);
                if (! $ok) {
                    Log::warning('live_sync.transmit_failed', [
                        'url' => $url,
                        'http_status' => $response->status(),
                        'body' => Str::limit($response->body(), 500),
                    ]);
                }

                return [
                    'ok' => $ok,
                    'status' => $response->status(),
                    'body' => $json ?? $response->body(),
                    'message' => $ok
                        ? (string) ($json['message'] ?? 'OK')
                        : (string) ($json['message'] ?? $response->reason() ?: 'Receiver rejected request'),
                ];
            } catch (\Throwable $e) {
                if ($attempt >= $maxAttempts) {
                    Log::warning('live_sync.transmit_exception', [
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);

                    return [
                        'ok' => false,
                        'message' => $e->getMessage(),
                    ];
                }
                usleep(2_000_000);
            }
        }

        return ['ok' => false, 'message' => 'Transmit failed after retries'];
    }

    /**
     * @return array{timestamp: string, nonce: string, signature: string}
     */
    private function signRequest(string $path, string $body): array
    {
        $timestamp = (string) time();
        $nonce = (string) Str::uuid();
        $bodyHash = hash('sha256', $body);
        $canonical = implode("\n", ['POST', $path, $timestamp, $nonce, $bodyHash]);
        $signature = hash_hmac('sha256', $canonical, (string) config('services.live_sync.secret'));

        return [
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => $signature,
        ];
    }

    private function receiverUrl(): string
    {
        return rtrim((string) config('services.live_sync.receiver_url'), '/');
    }

    private function receiverPath(): string
    {
        $path = (string) config('services.live_sync.receiver_path', '/api/v1/sync/live');
        if ($path === '' || $path[0] !== '/') {
            $path = '/'.ltrim($path, '/');
        }

        return $path;
    }
}
