<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        $business = Auth::guard('business')->user();

        // Date range (default to last 30 days)
        $dateFrom = $request->filled('date_from') ? $request->date_from : now()->subDays(30)->format('Y-m-d');
        $dateTo = $request->filled('date_to') ? $request->date_to : now()->format('Y-m-d');

        // Overall statistics
        $stats = [
            'total_transactions' => $business->payments()->count(),
            'total_approved' => $business->payments()->where('status', 'approved')->count(),
            'total_pending' => $business->payments()->where('status', 'pending')->count(),
            'total_rejected' => $business->payments()->where('status', 'rejected')->count(),
            'total_revenue' => $business->payments()->where('status', 'approved')->sum('amount'),
            'average_transaction' => $business->payments()->where('status', 'approved')->avg('amount'),
        ];

        // Daily statistics for the selected period
        $dailyStats = $business->payments()
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN amount ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Status breakdown
        $statusBreakdown = $business->payments()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Monthly revenue
        $monthlyRevenue = $business->payments()
            ->where('status', 'approved')
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->take(12)
            ->get();

        return view('business.statistics.index', compact(
            'stats',
            'dailyStats',
            'statusBreakdown',
            'monthlyRevenue',
            'dateFrom',
            'dateTo'
        ));
    }
}
