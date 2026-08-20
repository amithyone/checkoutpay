<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use App\Services\Credit\BusinessPeerLoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeerLoanCronController extends Controller
{
    public function collectDaily(Request $request, BusinessPeerLoanService $loanService): JsonResponse
    {
        return $this->runCollect($request, $loanService, 'daily');
    }

    public function collectWeekly(Request $request, BusinessPeerLoanService $loanService): JsonResponse
    {
        return $this->runCollect($request, $loanService, 'weekly');
    }

    public function collectMonthly(Request $request, BusinessPeerLoanService $loanService): JsonResponse
    {
        return $this->runCollect($request, $loanService, 'monthly');
    }

    private function runCollect(Request $request, BusinessPeerLoanService $loanService, string $cadence): JsonResponse
    {
        $start = microtime(true);
        try {
            $count = $loanService->collectDue($cadence);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Peer loan cron collect failed', [
                'cadence' => $cadence,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
                'cadence' => $cadence,
                'timestamp' => now()->toDateTimeString(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Peer loan installments processed',
            'cadence' => $cadence,
            'collections' => $count,
            'execution_time_seconds' => round(microtime(true) - $start, 2),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
