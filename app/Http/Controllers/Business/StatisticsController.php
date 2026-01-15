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

        // Period selector (daily, monthly, yearly)
        $period = $request->get('period', 'monthly'); // daily, monthly, yearly
        
        // Date range (default based on period)
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = $request->date_from;
            $dateTo = $request->date_to;
        } else {
            switch ($period) {
                case 'daily':
                    $dateFrom = now()->subDays(30)->format('Y-m-d');
                    $dateTo = now()->format('Y-m-d');
                    break;
                case 'yearly':
                    $dateFrom = now()->subYears(2)->startOfYear()->format('Y-m-d');
                    $dateTo = now()->format('Y-m-d');
                    break;
                default: // monthly
                    $dateFrom = now()->subMonths(12)->startOfMonth()->format('Y-m-d');
                    $dateTo = now()->format('Y-m-d');
            }
        }

        // Overall statistics
        $stats = [
            'total_transactions' => $business->payments()->count(),
            'total_approved' => $business->payments()->where('status', 'approved')->count(),
            'total_pending' => $business->payments()->where('status', 'pending')->count(),
            'total_rejected' => $business->payments()->where('status', 'rejected')->count(),
            'total_revenue' => $business->payments()->where('status', 'approved')->sum('amount'),
            'average_transaction' => $business->payments()->where('status', 'approved')->avg('amount'),
        ];

        // Website-specific statistics
        $websiteStats = [];
        foreach ($business->websites as $website) {
            // Daily stats for this website
            $dailyStats = $website->payments()
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN amount ELSE 0 END) as revenue'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count'),
                    DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count'),
                    DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected_count')
                )
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            // Monthly stats for this website
            $monthlyStats = $website->payments()
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN amount ELSE 0 END) as revenue'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count'),
                    DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count'),
                    DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected_count')
                )
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get();

            // Yearly stats for this website
            $yearlyStats = $website->payments()
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN amount ELSE 0 END) as revenue'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count'),
                    DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count'),
                    DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected_count')
                )
                ->groupBy('year')
                ->orderBy('year', 'desc')
                ->get();

            // Overall website stats
            $totalRevenue = $website->payments()->where('status', 'approved')->sum('amount');
            $totalPayments = $website->payments()->count();
            $approvedPayments = $website->payments()->where('status', 'approved')->count();
            $pendingPayments = $website->payments()->where('status', 'pending')->count();
            $rejectedPayments = $website->payments()->where('status', 'rejected')->count();
            $averageTransaction = $approvedPayments > 0 ? $totalRevenue / $approvedPayments : 0;
            $approvalRate = $totalPayments > 0 ? ($approvedPayments / $totalPayments) * 100 : 0;

            // Today's stats
            $todayRevenue = $website->payments()
                ->where('status', 'approved')
                ->whereDate('created_at', today())
                ->sum('amount');
            $todayPayments = $website->payments()
                ->whereDate('created_at', today())
                ->count();

            // This month's stats
            $monthlyRevenue = $website->payments()
                ->where('status', 'approved')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum('amount');
            $monthlyPayments = $website->payments()
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();

            // This year's stats
            $yearlyRevenue = $website->payments()
                ->where('status', 'approved')
                ->whereYear('created_at', now()->year)
                ->sum('amount');
            $yearlyPayments = $website->payments()
                ->whereYear('created_at', now()->year)
                ->count();

            $websiteStats[] = [
                'website' => $website,
                'total_revenue' => $totalRevenue,
                'total_payments' => $totalPayments,
                'approved_payments' => $approvedPayments,
                'pending_payments' => $pendingPayments,
                'rejected_payments' => $rejectedPayments,
                'average_transaction' => $averageTransaction,
                'approval_rate' => $approvalRate,
                'today_revenue' => $todayRevenue,
                'today_payments' => $todayPayments,
                'monthly_revenue' => $monthlyRevenue,
                'monthly_payments' => $monthlyPayments,
                'yearly_revenue' => $yearlyRevenue,
                'yearly_payments' => $yearlyPayments,
                'daily_stats' => $dailyStats,
                'monthly_stats' => $monthlyStats,
                'yearly_stats' => $yearlyStats,
            ];
        }

        // Sort by total revenue descending
        usort($websiteStats, function($a, $b) {
            return $b['total_revenue'] <=> $a['total_revenue'];
        });

        // Overall daily statistics for the selected period
        $dailyStats = $business->payments()
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN amount ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Overall monthly statistics
        $monthlyStats = $business->payments()
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN amount ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        // Overall yearly statistics
        $yearlyStats = $business->payments()
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN amount ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count')
            )
            ->groupBy('year')
            ->orderBy('year', 'desc')
            ->get();

        // Status breakdown
        $statusBreakdown = $business->payments()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Monthly revenue (for backward compatibility)
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
            'websiteStats',
            'dailyStats',
            'monthlyStats',
            'yearlyStats',
            'statusBreakdown',
            'monthlyRevenue',
            'dateFrom',
            'dateTo',
            'period'
        ));
    }
}
