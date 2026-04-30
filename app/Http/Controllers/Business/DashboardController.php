<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $business = Auth::guard('business')->user();
        // Revenue periods should reflect when a payment was approved, not when it was created.
        // matched_at is the primary approval timestamp in this codebase.
        $approvedAtExpr = DB::raw('COALESCE(matched_at, created_at)');
        $nigeriaTz = 'Africa/Lagos';
        $nigeriaNow = now($nigeriaTz);
        $todayStartUtc = $nigeriaNow->copy()->startOfDay()->utc();
        $todayEndUtc = $nigeriaNow->copy()->endOfDay()->utc();
        $monthStartUtc = $nigeriaNow->copy()->startOfMonth()->utc();
        $monthEndUtc = $nigeriaNow->copy()->endOfMonth()->utc();
        $yearStartUtc = $nigeriaNow->copy()->startOfYear()->utc();
        $yearEndUtc = $nigeriaNow->copy()->endOfYear()->utc();

        // Calculate business revenue from actual transactions (not edited values)
        // Today's revenue: sum of all approved payments for Nigeria's current day window
        $todayRevenue = $business->payments()
            ->where('status', 'approved')
            ->whereBetween($approvedAtExpr, [$todayStartUtc, $todayEndUtc])
            ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;
        
        // Monthly revenue: Nigeria calendar month window
        $monthlyRevenue = $business->payments()
            ->where('status', 'approved')
            ->whereBetween($approvedAtExpr, [$monthStartUtc, $monthEndUtc])
            ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;
        
        // Yearly revenue: Nigeria calendar year window
        $yearlyRevenue = $business->payments()
            ->where('status', 'approved')
            ->whereBetween($approvedAtExpr, [$yearStartUtc, $yearEndUtc])
            ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;

        // Get statistics
        $stats = [
            'total_payments' => $business->payments()->count(),
            'approved_payments' => $business->payments()->where('status', 'approved')->count(),
            'pending_payments' => $business->payments()->where('status', 'pending')->count(),
            'rejected_payments' => $business->payments()->where('status', 'rejected')->count(),
            'total_withdrawals' => $business->withdrawalRequests()->count(),
            'pending_withdrawals' => $business->withdrawalRequests()->where('status', 'pending')->count(),
            'total_revenue' => $business->payments()->where('status', 'approved')->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0,
            'today_revenue' => $todayRevenue, // Calculated from actual transactions
            'monthly_revenue' => $monthlyRevenue, // Calculated from actual transactions
            'yearly_revenue' => $yearlyRevenue, // Calculated from actual transactions
            'balance' => $business->balance,
            'ledger_balance' => $business->getLedgerBalance(),
            'overdraft_limit' => (float) $business->overdraft_limit,
            'available_balance' => $business->getAvailableBalance(),
            'has_overdraft' => $business->hasOverdraftApproved(),
            'overdraft_status' => $business->overdraft_status,
        ];

        // Get website statistics with daily and monthly breakdowns
        $websiteStats = [];
        foreach ($business->websites as $website) {
            // Enforce both business_id and business_website_id ownership filters.
            $websitePaymentsQuery = $business->payments()
                ->where('business_id', $business->id)
                ->where('business_website_id', $website->id);
            
            // Calculate revenue from actual transactions
            $todayRevenue = (clone $websitePaymentsQuery)
                ->where('status', 'approved')
                ->whereBetween($approvedAtExpr, [$todayStartUtc, $todayEndUtc])
                ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;
            
            // Calculate monthly/yearly revenue from actual transactions
            $monthlyRevenue = (clone $websitePaymentsQuery)
                ->where('status', 'approved')
                ->whereBetween($approvedAtExpr, [$monthStartUtc, $monthEndUtc])
                ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;
            $yearlyRevenue = (clone $websitePaymentsQuery)
                ->where('status', 'approved')
                ->whereBetween($approvedAtExpr, [$yearStartUtc, $yearEndUtc])
                ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;
            
            // Calculate daily payments count
            $todayPayments = (clone $websitePaymentsQuery)
                ->where('status', 'approved')
                ->whereBetween($approvedAtExpr, [$todayStartUtc, $todayEndUtc])
                ->count();
            
            // Calculate monthly payments count
            $monthlyPayments = (clone $websitePaymentsQuery)
                ->where('status', 'approved')
                ->whereBetween($approvedAtExpr, [$monthStartUtc, $monthEndUtc])
                ->count();
            
            $websiteStats[] = [
                'website' => $website,
                'total_revenue' => (clone $websitePaymentsQuery)
                    ->where('status', 'approved')
                    ->sum(DB::raw('COALESCE(business_receives, amount)')) ?? 0,
                'total_payments' => (clone $websitePaymentsQuery)->where('status', 'approved')->count(),
                'pending_payments' => (clone $websitePaymentsQuery)->where('status', 'pending')->count(),
                'today_revenue' => $todayRevenue, // Calculated from actual transactions
                'today_payments' => $todayPayments,
                'monthly_revenue' => $monthlyRevenue, // Calculated from actual transactions
                'yearly_revenue' => $yearlyRevenue, // Calculated from actual transactions
                'monthly_payments' => $monthlyPayments,
            ];
        }
        // Sort by revenue descending
        usort($websiteStats, function($a, $b) {
            return $b['total_revenue'] <=> $a['total_revenue'];
        });

        // Recent payments with website
        $recentPayments = $business->payments()
            ->with('website')
            ->latest()
            ->take(10)
            ->get();

        // Recent withdrawals
        $recentWithdrawals = $business->withdrawalRequests()
            ->latest()
            ->take(5)
            ->get();

        return view('business.dashboard', compact('stats', 'websiteStats', 'recentPayments', 'recentWithdrawals'));
    }
}
