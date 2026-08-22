<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LiveSync\LiveSyncGenericEngine;
use App\Services\LiveSyncIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LiveSyncReceiverController extends Controller
{
    public function receive(Request $request, LiveSyncIngestionService $sync, LiveSyncGenericEngine $engine): JsonResponse
    {
        $entities = array_keys((array) config('live_sync.entities', []));

        $validator = Validator::make($request->all(), [
            'event_id' => ['required', 'string', 'uuid'],
            'source' => ['nullable', 'string', 'max:100'],
            'entity' => ['required', Rule::in($entities)],
            'operation' => ['required', Rule::in(['upsert', 'delete'])],
            'sent_at' => ['nullable', 'date'],
            'data' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $sync->ingest($validator->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Live sync receiver failed', [
                'error' => $e->getMessage(),
                'event_id' => (string) $request->input('event_id', ''),
                'entity' => (string) $request->input('entity', ''),
                'operation' => (string) $request->input('operation', ''),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process sync event',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['status'] === 'duplicate' ? 'Duplicate event ignored' : 'Sync processed',
            'data' => $result,
        ]);
    }

    /**
     * Lightweight existence check so Namecheap only pushes missing keys.
     */
    public function probe(Request $request, LiveSyncGenericEngine $engine): JsonResponse
    {
        $entities = array_keys((array) config('live_sync.entities', []));

        $validator = Validator::make($request->all(), [
            'entity' => ['required', Rule::in($entities)],
            'keys' => ['required', 'array', 'min:1', 'max:500'],
            'keys.*' => ['required', 'string', 'max:191'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $entity = (string) $request->input('entity');
        /** @var list<string> $keys */
        $keys = array_values(array_unique(array_map(
            static fn ($k) => trim((string) $k),
            (array) $request->input('keys', []),
        )));
        $keys = array_values(array_filter($keys, static fn ($k) => $k !== ''));

        $result = $engine->probe($entity, $keys);

        return response()->json([
            'success' => true,
            'message' => 'Probe complete',
            'data' => [
                'entity' => $entity,
                'checked' => count($keys),
                'missing' => $result['missing'],
                'present' => $result['present'],
            ],
        ]);
    }

    /**
     * Batch upsert/delete — one HTTP round-trip for many rows (gap-fill / catch-up).
     */
    public function receiveBatch(Request $request, LiveSyncIngestionService $sync): JsonResponse
    {
        $entities = array_keys((array) config('live_sync.entities', []));
        $maxEvents = max(1, min(50, (int) config('live_sync.batch.max_events', 50)));

        $validator = Validator::make($request->all(), [
            'events' => ['required', 'array', 'min:1', 'max:'.$maxEvents],
            'events.*.event_id' => ['required', 'string', 'uuid'],
            'events.*.source' => ['nullable', 'string', 'max:100'],
            'events.*.entity' => ['required', Rule::in($entities)],
            'events.*.operation' => ['required', Rule::in(['upsert', 'delete'])],
            'events.*.insert_only' => ['nullable', 'boolean'],
            'events.*.sent_at' => ['nullable', 'date'],
            'events.*.data' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var list<array<string, mixed>> $events */
        $events = array_values((array) $request->input('events', []));

        try {
            $summary = $sync->ingestBatch($events);
        } catch (\Throwable $e) {
            Log::error('Live sync batch receiver failed', [
                'error' => $e->getMessage(),
                'count' => count($events),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process sync batch',
            ], 500);
        }

        $failed = (int) ($summary['failed'] ?? 0);

        return response()->json([
            'success' => $failed === 0,
            'message' => $failed === 0 ? 'Batch processed' : "Batch completed with {$failed} failure(s)",
            'data' => $summary,
        ], $failed === 0 ? 200 : 207);
    }
}
