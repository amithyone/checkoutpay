<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $business = Auth::guard('business')->user();

        // Get statistics
        $stats = [
            'total_payments' => $business->payments()->count(),
            'approved_payments' => $business->payments()->where('status', 'approved')->count(),
            'pending_payments' => $business->payments()->where('status', 'pending')->count(),
            'rejected_payments' => $business->payments()->where('status', 'rejected')->count(),
            'total_withdrawals' => $business->withdrawalRequests()->count(),
            'pending_withdrawals' => $business->withdrawalRequests()->where('status', 'pending')->count(),
            'total_revenue' => $business->payments()->where('status', 'approved')->sum('amount'),
            'today_revenue' => $business->payments()
                ->where('status', 'approved')
                ->where(function($query) {
                    $query->whereDate('matched_at', today())
                          ->orWhere(function($q) {
                              // Fallback to created_at if matched_at is null (for older payments)
                              $q->whereNull('matched_at')
                                ->whereDate('created_at', today());
                          });
                })
                ->sum('amount'),
            'balance' => $business->balance,
        ];

        // Get website statistics with daily and monthly breakdowns
        $websiteStats = [];
        foreach ($business->websites as $website) {
            $websitePayments = $website->payments()->where('status', 'approved')->get();
            
            // Calculate daily revenue (today)
            $todayRevenue = $website->payments()
                ->where('status', 'approved')
                ->whereDate('created_at', today())
                ->sum('amount');
            
            // Calculate monthly revenue (this month)
            $monthlyRevenue = $website->payments()
                ->where('status', 'approved')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum('amount');
            
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
                'total_revenue' => $websitePayments->sum('amount'),
                'total_payments' => $websitePayments->count(),
                'pending_payments' => $website->payments()->where('status', 'pending')->count(),
                'today_revenue' => $todayRevenue,
                'today_payments' => $todayPayments,
                'monthly_revenue' => $monthlyRevenue,
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
