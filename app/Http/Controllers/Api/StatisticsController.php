<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    /**
     * Get payment statistics
     */
    public function index(Request $request): JsonResponse
    {
        $dateFrom = $request->get('from_date', now()->subMonth());
        $dateTo = $request->get('to_date', now());

        $query = Payment::whereBetween('created_at', [$dateFrom, $dateTo]);

        // Total counts by status
        $stats = [
            'total' => $query->count(),
            'pending' => (clone $query)->where('status', Payment::STATUS_PENDING)->count(),
            'approved' => (clone $query)->where('status', Payment::STATUS_APPROVED)->count(),
            'rejected' => (clone $query)->where('status', Payment::STATUS_REJECTED)->count(),
            'expired' => (clone $query)->expired()->count(),
        ];

        // Amount statistics
        $amountStats = (clone $query)
            ->where('status', Payment::STATUS_APPROVED)
            ->select(
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('AVG(amount) as average_amount'),
                DB::raw('MIN(amount) as min_amount'),
                DB::raw('MAX(amount) as max_amount'),
                DB::raw('COUNT(*) as count')
            )
            ->first();

        // Success rate
        $successRate = $stats['total'] > 0 
            ? round(($stats['approved'] / $stats['total']) * 100, 2)
            : 0;

        // Daily breakdown
        $dailyBreakdown = (clone $query)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved'),
                DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'statistics' => [
                'counts' => $stats,
                'amounts' => [
                    'total' => (float) ($amountStats->total_amount ?? 0),
                    'average' => (float) ($amountStats->average_amount ?? 0),
                    'min' => (float) ($amountStats->min_amount ?? 0),
                    'max' => (float) ($amountStats->max_amount ?? 0),
                    'count' => (int) ($amountStats->count ?? 0),
                ],
                'success_rate' => $successRate,
            ],
            'daily_breakdown' => $dailyBreakdown,
        ]);
    }
}
