<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        $business = Auth::guard('business')->user();
        $nigeriaTz = 'Africa/Lagos';
        $approvedAtExpr = DB::raw('COALESCE(matched_at, created_at)');
        $approvedAtNigeriaDateExpr = DB::raw("DATE(CONVERT_TZ(COALESCE(matched_at, created_at), '+00:00', '+01:00'))");
        $createdAtNigeriaDateExpr = DB::raw("DATE(CONVERT_TZ(created_at, '+00:00', '+01:00'))");

        // Period selector (daily, monthly, yearly)
        $period = $request->get('period', 'monthly'); // daily, monthly, yearly
        
        // Date range (default based on period)
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = $request->date_from;
            $dateTo = $request->date_to;
        } else {
            $nigeriaNow = now($nigeriaTz);
            switch ($period) {
                case 'daily':
                    $dateFrom = $nigeriaNow->copy()->subDays(30)->format('Y-m-d');
                    $dateTo = $nigeriaNow->format('Y-m-d');
                    break;
                case 'yearly':
                    $dateFrom = $nigeriaNow->copy()->subYears(2)->startOfYear()->format('Y-m-d');
                    $dateTo = $nigeriaNow->format('Y-m-d');
                    break;
                default: // monthly
                    $dateFrom = $nigeriaNow->copy()->subMonths(12)->startOfMonth()->format('Y-m-d');
                    $dateTo = $nigeriaNow->format('Y-m-d');
            }
        }

        $rangeStartUtc = Carbon::parse($dateFrom, $nigeriaTz)->startOfDay()->utc();
        $rangeEndUtc = Carbon::parse($dateTo, $nigeriaTz)->endOfDay()->utc();
        $todayStartUtc = now($nigeriaTz)->startOfDay()->utc();
        $todayEndUtc = now($nigeriaTz)->endOfDay()->utc();
        $monthStartUtc = now($nigeriaTz)->startOfMonth()->utc();
        $monthEndUtc = now($nigeriaTz)->endOfMonth()->utc();
        $yearStartUtc = now($nigeriaTz)->startOfYear()->utc();
        $yearEndUtc = now($nigeriaTz)->endOfYear()->utc();

        // Calculate business revenue from actual transactions (not edited values)
        $todayRevenue = $business->payments()
            ->where('status', 'approved')
            ->whereBetween($approvedAtExpr, [$todayStartUtc, $todayEndUtc])
            ->sum(DB::raw('COALESCE(business_receives, amount)')) ?? 0;
        
        $monthlyRevenue = $business->payments()
            ->where('status', 'approved')
            ->whereBetween($approvedAtExpr, [$monthStartUtc, $monthEndUtc])
            ->sum(DB::raw('COALESCE(business_receives, amount)')) ?? 0;
        
        $yearlyRevenue = $business->payments()
            ->where('status', 'approved')
            ->whereBetween($approvedAtExpr, [$yearStartUtc, $yearEndUtc])
            ->sum(DB::raw('COALESCE(business_receives, amount)')) ?? 0;

        // Overall statistics
        $stats = [
            'total_transactions' => $business->payments()->count(),
            'total_approved' => $business->payments()->where('status', 'approved')->count(),
            'total_pending' => $business->payments()->where('status', 'pending')->count(),
            'total_rejected' => $business->payments()->where('status', 'rejected')->count(),
            'total_revenue' => $business->payments()->where('status', 'approved')->sum(DB::raw('COALESCE(business_receives, amount)')) ?? 0,
            'today_revenue' => $todayRevenue, // Calculated from actual transactions
            'monthly_revenue' => $monthlyRevenue, // Calculated from actual transactions
            'yearly_revenue' => $yearlyRevenue, // Calculated from actual transactions
            'average_transaction' => $business->payments()->where('status', 'approved')->avg(DB::raw('COALESCE(business_receives, amount)')),
        ];

        // Website-specific statistics
        $websiteStats = [];
        foreach ($business->websites as $website) {
            $websitePayments = $business->payments()
                ->where('business_id', $business->id)
                ->where('business_website_id', $website->id);

            // Daily stats for this website
            $dailyStats = (clone $websitePayments)
                ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc])
                ->select(
                    $createdAtNigeriaDateExpr.' as date',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN COALESCE(business_receives, amount) ELSE 0 END) as revenue'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count'),
                    DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count'),
                    DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected_count')
                )
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            // Monthly stats for this website
            $monthlyStats = (clone $websitePayments)
                ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc])
                ->select(
                    DB::raw("YEAR(CONVERT_TZ(created_at, '+00:00', '+01:00')) as year"),
                    DB::raw("MONTH(CONVERT_TZ(created_at, '+00:00', '+01:00')) as month"),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN COALESCE(business_receives, amount) ELSE 0 END) as revenue'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count'),
                    DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count'),
                    DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected_count')
                )
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get();

            // Yearly stats for this website
            $yearlyStats = (clone $websitePayments)
                ->select(
                    DB::raw("YEAR(CONVERT_TZ(created_at, '+00:00', '+01:00')) as year"),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN COALESCE(business_receives, amount) ELSE 0 END) as revenue'),
                    DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count'),
                    DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_count'),
                    DB::raw('SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected_count')
                )
                ->groupBy('year')
                ->orderBy('year', 'desc')
                ->get();

            // Overall website stats
            $totalRevenue = (clone $websitePayments)->where('status', 'approved')->sum(DB::raw('COALESCE(business_receives, amount)'));
            $totalPayments = (clone $websitePayments)->count();
            $approvedPayments = (clone $websitePayments)->where('status', 'approved')->count();
            $pendingPayments = (clone $websitePayments)->where('status', 'pending')->count();
            $rejectedPayments = (clone $websitePayments)->where('status', 'rejected')->count();
            $averageTransaction = $approvedPayments > 0 ? $totalRevenue / $approvedPayments : 0;
            $approvalRate = $totalPayments > 0 ? ($approvedPayments / $totalPayments) * 100 : 0;

            // Today's stats - calculate from actual transactions
            $todayRevenue = (clone $websitePayments)
                ->where('status', 'approved')
                ->whereBetween($approvedAtExpr, [$todayStartUtc, $todayEndUtc])
                ->sum(DB::raw('COALESCE(business_receives, amount)')) ?? 0;
            $todayPayments = (clone $websitePayments)
                ->whereBetween($approvedAtExpr, [$todayStartUtc, $todayEndUtc])
                ->count();

            // This month's stats - calculate from actual transactions
            $monthlyRevenue = (clone $websitePayments)
                ->where('status', 'approved')
                ->whereBetween($approvedAtExpr, [$monthStartUtc, $monthEndUtc])
                ->sum(DB::raw('COALESCE(business_receives, amount)')) ?? 0;
            $monthlyPayments = (clone $websitePayments)
                ->where('status', 'approved')
                ->whereBetween($approvedAtExpr, [$monthStartUtc, $monthEndUtc])
                ->count();

            // This year's stats - calculate from actual transactions
            $yearlyRevenue = (clone $websitePayments)
                ->where('status', 'approved')
                ->whereBetween($approvedAtExpr, [$yearStartUtc, $yearEndUtc])
                ->sum(DB::raw('COALESCE(business_receives, amount)')) ?? 0;
            $yearlyPayments = (clone $websitePayments)
                ->where('status', 'approved')
                ->whereBetween($approvedAtExpr, [$yearStartUtc, $yearEndUtc])
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
            ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc])
            ->select(
                $createdAtNigeriaDateExpr.' as date',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN COALESCE(business_receives, amount) ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Overall monthly statistics
        $monthlyStats = $business->payments()
            ->whereBetween('created_at', [$rangeStartUtc, $rangeEndUtc])
            ->select(
                DB::raw("YEAR(CONVERT_TZ(created_at, '+00:00', '+01:00')) as year"),
                DB::raw("MONTH(CONVERT_TZ(created_at, '+00:00', '+01:00')) as month"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN COALESCE(business_receives, amount) ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        // Overall yearly statistics
        $yearlyStats = $business->payments()
            ->select(
                DB::raw("YEAR(CONVERT_TZ(created_at, '+00:00', '+01:00')) as year"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status = "approved" THEN COALESCE(business_receives, amount) ELSE 0 END) as revenue'),
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
                DB::raw("YEAR(CONVERT_TZ(created_at, '+00:00', '+01:00')) as year"),
                DB::raw("MONTH(CONVERT_TZ(created_at, '+00:00', '+01:00')) as month"),
                DB::raw('SUM(COALESCE(business_receives, amount)) as revenue'),
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
