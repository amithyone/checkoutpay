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

    private function ingestUnsafe(array $payload): array
    {
        $eventId = (string) $payload['event_id'];
        $entity = (string) $payload['entity'];
        $operation = (string) $payload['operation'];
        $source = isset($payload['source']) ? (string) $payload['source'] : null;
        $data = (array) $payload['data'];

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
            $recordKey = DB::transaction(function () use ($entity, $operation, $data) {
                return $this->engine->upsert($entity, $data, $operation);
            });

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
