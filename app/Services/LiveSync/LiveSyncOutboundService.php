<?php

namespace App\Services\LiveSync;

use App\Jobs\LiveSyncPushJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Builds payloads and pushes (or queues) them to the Contabo receiver.
 */
final class LiveSyncOutboundService
{
    private static bool $suppress = false;

    public function __construct(
        private LiveSyncTransmitterClient $client,
        private LiveSyncGenericEngine $engine,
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

    public function pushEntity(string $entity, Model $model, string $operation = 'upsert'): void
    {
        if (! $this->shouldTransmit()) {
            return;
        }

        try {
            $data = $operation === 'delete'
                ? [
                    '_origin_id' => (int) $model->getKey(),
                    '_natural_key' => $this->engine->probeKeyForModel($entity, $model),
                ]
                : $this->engine->serialize($entity, $model);
        } catch (\Throwable $e) {
            Log::warning('live_sync.serialize_failed', [
                'entity' => $entity,
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $this->dispatch($entity, $operation, $data);
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
     * @param  list<array{entity: string, operation: string, data: array<string, mixed>, insert_only?: bool}>  $items
     * @return array{ok: bool, message: string, processed?: int, failed?: int, status?: int, body?: mixed}
     */
    public function pushBatchNow(array $items, bool $insertOnly = false): array
    {
        if ($items === []) {
            return ['ok' => true, 'message' => 'No items', 'processed' => 0, 'failed' => 0];
        }

        $source = (string) config('services.live_sync.source_name', 'namecheap-live');
        $sentAt = now()->toIso8601String();
        $events = [];
        foreach ($items as $item) {
            $events[] = [
                'event_id' => (string) Str::uuid(),
                'source' => $source,
                'entity' => (string) $item['entity'],
                'operation' => (string) ($item['operation'] ?? 'upsert'),
                'insert_only' => $insertOnly || (bool) ($item['insert_only'] ?? false),
                'sent_at' => $sentAt,
                'data' => (array) ($item['data'] ?? []),
            ];
        }

        return $this->client->sendBatch($events);
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
