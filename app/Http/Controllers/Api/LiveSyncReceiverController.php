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
}
