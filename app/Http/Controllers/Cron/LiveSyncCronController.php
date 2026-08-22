<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * HTTP cron trigger for Namecheap → Contabo live sync (transmitter only).
 */
class LiveSyncCronController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        if (! (bool) config('services.live_sync.transmit_enabled', false)) {
            return response()->json([
                'success' => false,
                'message' => 'Live sync transmitter is not enabled on this host (Namecheap only).',
            ], 503);
        }

        config(['services.live_sync.queue' => false]);

        $mode = strtolower(trim((string) $request->query('mode', 'incremental')));
        $includeFloat = $request->boolean('float') || $mode === 'full';

        $started = microtime(true);
        $steps = [];

        if ($includeFloat) {
            $code = Artisan::call('live-sync:push', [
                '--entity' => 'float',
                '--mode' => 'recent',
                '--force-all' => true,
                '--limit' => 500,
                '--sync' => true,
                '--chunk' => 25,
            ]);
            $steps['float'] = [
                'exit_code' => $code,
                'output' => trim(Artisan::output()),
            ];
        }

        if ($mode === 'full') {
            $code = Artisan::call('live-sync:fill-gaps', [
                '--entity' => (string) $request->query('entity', 'common'),
                '--until-done' => true,
                '--sync' => true,
                '--no-probe' => $request->boolean('no-probe'),
            ]);
            $steps['fill_gaps'] = [
                'exit_code' => $code,
                'output' => trim(Artisan::output()),
            ];
        } else {
            $code = Artisan::call('live-sync:incremental', [
                '--entity' => (string) $request->query('entity', 'common'),
                '--sync' => true,
            ]);
            $steps['incremental'] = [
                'exit_code' => $code,
                'output' => trim(Artisan::output()),
            ];
        }

        $failed = collect($steps)->contains(fn ($step) => (int) ($step['exit_code'] ?? 1) !== 0);

        return response()->json([
            'success' => ! $failed,
            'message' => $failed ? 'Live sync completed with errors' : 'Live sync completed',
            'mode' => $mode,
            'float_included' => $includeFloat,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'steps' => $steps,
            'timestamp' => now()->toIso8601String(),
        ], $failed ? 500 : 200);
    }
}
