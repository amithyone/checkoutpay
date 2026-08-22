<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use App\Services\WalletImport\FormCsvTier2BatchProvisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * HTTP cron trigger for gradual Tier-2 Mevon VA provisioning (cron-job.org / EasyCron).
 */
class WalletTier2BatchCronController extends Controller
{
    public function provision(Request $request, FormCsvTier2BatchProvisionService $batch): JsonResponse
    {
        $start = microtime(true);

        $limit = (int) $request->query('limit', 8);
        $limit = max(1, min(50, $limit));

        $apply = $request->boolean('apply', true);
        $jsonl = $request->query('jsonl');
        $jsonlPath = is_string($jsonl) && trim($jsonl) !== '' ? trim($jsonl) : null;

        try {
            $stats = $batch->run($limit, $apply, $jsonlPath);
        } catch (\Throwable $e) {
            Log::error('wallet_tier2_batch.cron_failed', [
                'error' => $e->getMessage(),
                'limit' => $limit,
                'apply' => $apply,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
                'method' => 'wallet_tier2_batch',
                'timestamp' => now()->toDateTimeString(),
            ], 500);
        }

        $remainingHint = max(
            0,
            $stats['candidates'] - $stats['skipped_has_va'] - $stats['skipped_in_flight']
        );
        $perDay = max(1, $limit * 2);
        $daysEta = (int) ceil($remainingHint / $perDay);

        return response()->json([
            'success' => true,
            'message' => $apply
                ? 'Tier-2 batch provision run completed'
                : 'Tier-2 batch dry-run completed (pass apply=1 to queue)',
            'method' => 'wallet_tier2_batch',
            'timestamp' => now()->toDateTimeString(),
            'execution_time_seconds' => round(microtime(true) - $start, 2),
            'apply' => $apply,
            'limit' => $limit,
            'stats' => $stats,
            'eta_days_at_twice_daily' => $daysEta,
            'remaining_cohort_hint' => $remainingHint,
        ]);
    }
}
