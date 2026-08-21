<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LiveSyncIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LiveSyncReceiverController extends Controller
{
    public function receive(Request $request, LiveSyncIngestionService $sync): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_id' => ['required', 'string', 'uuid'],
            'source' => ['nullable', 'string', 'max:100'],
            'entity' => ['required', Rule::in(['payment', 'business', 'renter'])],
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
    public function probe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity' => ['required', Rule::in(['payment', 'business', 'renter'])],
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

        $present = match ($entity) {
            'payment' => \App\Models\Payment::query()
                ->whereIn('transaction_id', $keys)
                ->pluck('transaction_id')
                ->map(fn ($v) => (string) $v)
                ->all(),
            'business' => \App\Models\Business::query()
                ->where(function ($q) use ($keys) {
                    $q->whereIn('business_id', $keys)->orWhereIn('email', $keys);
                })
                ->get(['business_id', 'email'])
                ->flatMap(fn ($b) => array_filter([(string) $b->business_id, strtolower((string) $b->email)]))
                ->unique()
                ->values()
                ->all(),
            'renter' => \App\Models\Renter::query()
                ->whereIn('email', array_map('strtolower', $keys))
                ->pluck('email')
                ->map(fn ($v) => strtolower((string) $v))
                ->all(),
            default => [],
        };

        $presentSet = [];
        foreach ($present as $p) {
            $presentSet[strtolower((string) $p)] = true;
            $presentSet[(string) $p] = true;
        }

        $missing = [];
        $found = [];
        foreach ($keys as $key) {
            if (isset($presentSet[$key]) || isset($presentSet[strtolower($key)])) {
                $found[] = $key;
            } else {
                $missing[] = $key;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Probe complete',
            'data' => [
                'entity' => $entity,
                'checked' => count($keys),
                'missing' => $missing,
                'present' => $found,
            ],
        ]);
    }
}
