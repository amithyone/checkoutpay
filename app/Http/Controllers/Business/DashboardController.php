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

        // Calculate business revenue from actual transactions (not edited values)
        // Today's revenue: sum of all approved payments for today
        $todayRevenue = $business->payments()
            ->where('status', 'approved')
            ->whereDate('created_at', today())
            ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;
        
        // Monthly revenue: sum of all approved payments for current month
        $monthlyRevenue = $business->payments()
            ->where('status', 'approved')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;
        
        // Yearly revenue: sum of all approved payments for current year
        $yearlyRevenue = $business->payments()
            ->where('status', 'approved')
            ->whereYear('created_at', now()->year)
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
        ];

        // Get website statistics with daily and monthly breakdowns
        $websiteStats = [];
        foreach ($business->websites as $website) {
            $websitePayments = $website->payments()->where('status', 'approved')->get();
            
            // Calculate revenue from actual transactions
            $todayRevenue = $website->payments()
                ->where('status', 'approved')
                ->whereDate('created_at', today())
                ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;
            
            // Calculate monthly/yearly revenue from actual transactions
            $monthlyRevenue = $website->payments()
                ->where('status', 'approved')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;
            $yearlyRevenue = $website->payments()
                ->where('status', 'approved')
                ->whereYear('created_at', now()->year)
                ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;
            
            // Calculate daily payments count
            $todayPayments = $website->payments()
                ->where('status', 'approved')
                ->whereDate('created_at', today())
                ->count();
            
            // Calculate monthly payments count
            $monthlyPayments = $website->payments()
                ->where('status', 'approved')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();
            
            $websiteStats[] = [
                'website' => $website,
                'total_revenue' => $websitePayments->sum('amount'), // Real transaction data for reference
                'total_payments' => $websitePayments->count(),
                'pending_payments' => $website->payments()->where('status', 'pending')->count(),
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
