<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Business\BusinessActivityFeedService;
use App\Services\Business\BusinessWebsiteStatsService;
use App\Services\Business\BusinessWhatsappWalletLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private BusinessActivityFeedService $activityFeed,
        private BusinessWebsiteStatsService $websiteStats,
        private BusinessWhatsappWalletLinkService $whatsappWalletLinks,
    ) {}

    public function index()
    {
        $business = Auth::guard('business')->user();
        // Revenue periods should reflect when a payment was approved, not when it was created.
        // matched_at is the primary approval timestamp in this codebase.
        $approvedAtExpr = DB::raw('COALESCE(matched_at, created_at)');
        $nigeriaTz = 'Africa/Lagos';
        $nigeriaNow = now($nigeriaTz);
        $nowUtc = $nigeriaNow->copy()->utc();
        $todayStartUtc = $nigeriaNow->copy()->startOfDay()->utc();
        $todayEndUtc = $nowUtc;
        $monthStartUtc = $nigeriaNow->copy()->startOfMonth()->utc();
        $monthEndUtc = $nowUtc;
        $yearStartUtc = $nigeriaNow->copy()->startOfYear()->utc();
        $yearEndUtc = $nowUtc;

        // Use payments table directly for deterministic stats (business_id scoped).
        $approvedBusinessPayments = Payment::query()
            ->where('business_id', $business->id)
            ->where('status', Payment::STATUS_APPROVED);

        // Calculate business revenue from actual transactions (not edited values)
        // Today's revenue: sum of all approved payments for Nigeria's current day window
        $todayRevenue = (clone $approvedBusinessPayments)
            ->whereBetween($approvedAtExpr, [$todayStartUtc, $todayEndUtc])
            ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;
        
        // Monthly revenue: Nigeria calendar month window
        $monthlyRevenue = (clone $approvedBusinessPayments)
            ->whereBetween($approvedAtExpr, [$monthStartUtc, $monthEndUtc])
            ->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0;
        
        // Yearly revenue: Nigeria calendar year window
        $yearlyRevenue = (clone $approvedBusinessPayments)
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
            'total_revenue' => (clone $approvedBusinessPayments)->sum(\DB::raw('COALESCE(business_receives, amount)')) ?? 0,
            'today_revenue' => $todayRevenue, // Calculated from actual transactions
            'monthly_revenue' => $monthlyRevenue, // Calculated from actual transactions
            'yearly_revenue' => $yearlyRevenue, // Calculated from actual transactions
            'balance' => $business->balance,
            'ledger_balance' => $business->getLedgerBalance(),
            'overdraft_limit' => (float) $business->overdraft_limit,
            'available_balance' => $business->getAvailableBalance(),
            'has_overdraft' => $business->hasOverdraftApproved(),
            'overdraft_status' => $business->overdraft_status,
            'overdraft_eligible' => (bool) $business->overdraft_eligible,
            'can_apply_overdraft' => $business->canApplyForOverdraft(),
            'peer_lending_borrow_eligible' => (bool) $business->peer_lending_borrow_eligible,
            'peer_lending_lend_eligible' => (bool) $business->peer_lending_lend_eligible,
        ];

        $websiteStats = $this->websiteStats->buildDashboardBreakdown($business);

        // Recent payments, loan repayments, and other balance activity
        $recentActivity = $this->activityFeed->recent($business, 10);

        // Recent withdrawals
        $recentWithdrawals = $business->withdrawalRequests()
            ->latest()
            ->take(5)
            ->get();

        $linkedWhatsappWallet = $this->whatsappWalletLinks->linkedWallet($business);

        return view('business.dashboard', compact(
            'stats',
            'websiteStats',
            'recentActivity',
            'recentWithdrawals',
            'linkedWhatsappWallet',
        ));
    }
}
