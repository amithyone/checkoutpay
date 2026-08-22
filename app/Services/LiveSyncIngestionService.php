<?php

namespace App\Services;

use App\Services\LiveSync\LiveSyncGenericEngine;
use App\Services\LiveSync\LiveSyncOutboundService;
use App\Models\LiveSyncEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LiveSyncIngestionService
{
    public function __construct(
        private LiveSyncGenericEngine $engine,
    ) {}

    public function ingest(array $payload): array
    {
        return LiveSyncOutboundService::withoutOutbound(function () use ($payload) {
            return $this->ingestUnsafe($payload);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{processed: int, duplicate: int, skipped_present: int, failed: int, results: list<array<string, mixed>>}
     */
    public function ingestBatch(array $events): array
    {
        return LiveSyncOutboundService::withoutOutbound(function () use ($events) {
            $summary = [
                'processed' => 0,
                'duplicate' => 0,
                'skipped_present' => 0,
                'failed' => 0,
                'results' => [],
            ];

            foreach ($events as $payload) {
                try {
                    $result = $this->ingestUnsafe((array) $payload);
                    $status = (string) ($result['status'] ?? 'processed');
                    if ($status === 'duplicate') {
                        $summary['duplicate']++;
                    } elseif ($status === 'skipped_present') {
                        $summary['skipped_present']++;
                    } else {
                        $summary['processed']++;
                    }
                    $summary['results'][] = $result;
                } catch (\Throwable $e) {
                    $summary['failed']++;
                    $summary['results'][] = [
                        'status' => 'failed',
                        'event_id' => (string) ($payload['event_id'] ?? ''),
                        'entity' => (string) ($payload['entity'] ?? ''),
                        'message' => \Illuminate\Support\Str::limit($e->getMessage(), 500, '...'),
                    ];
                }
            }

            return $summary;
        });
    }

    private function ingestUnsafe(array $payload): array
    {
        $eventId = (string) $payload['event_id'];
        $entity = (string) $payload['entity'];
        $operation = (string) $payload['operation'];
        $source = isset($payload['source']) ? (string) $payload['source'] : null;
        $data = (array) $payload['data'];
        $insertOnly = (bool) ($payload['insert_only'] ?? false);

        // Validate entity is configured
        $this->engine->entityConfig($entity);

        $existing = LiveSyncEvent::where('event_id', $eventId)->first();
        if ($existing) {
            return [
                'status' => 'duplicate',
                'event_id' => $eventId,
                'entity' => $existing->entity,
                'operation' => $existing->operation,
            ];
        }

        $event = LiveSyncEvent::create([
            'event_id' => $eventId,
            'source' => $source,
            'entity' => $entity,
            'operation' => $operation,
            'status' => 'pending',
            'payload' => Arr::only($payload, ['event_id', 'source', 'entity', 'operation', 'data', 'sent_at']),
        ]);

        try {
            $recordKey = DB::transaction(function () use ($entity, $operation, $data, $insertOnly) {
                if ($insertOnly && $operation === 'upsert' && $this->engine->findLocal($entity, $data) !== null) {
                    return '__skipped_present__';
                }

                return $this->engine->upsert($entity, $data, $operation, $insertOnly);
            });

            if ($recordKey === '__skipped_present__') {
                $event->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'error_message' => null,
                ]);

                return [
                    'status' => 'skipped_present',
                    'event_id' => $eventId,
                    'entity' => $entity,
                    'operation' => $operation,
                ];
            }

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);

            return [
                'status' => 'processed',
                'event_id' => $eventId,
                'entity' => $entity,
                'operation' => $operation,
                'record' => $recordKey,
            ];
        } catch (\Throwable $e) {
            $event->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000, '...'),
            ]);

            throw $e;
        }
    }
}
