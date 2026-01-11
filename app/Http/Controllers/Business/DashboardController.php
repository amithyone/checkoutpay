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
            'balance' => $business->balance,
        ];

        // Recent payments
        $recentPayments = $business->payments()
            ->latest()
            ->take(10)
            ->get();

        // Recent withdrawals
        $recentWithdrawals = $business->withdrawalRequests()
            ->latest()
            ->take(5)
            ->get();

        return view('business.dashboard', compact('stats', 'recentPayments', 'recentWithdrawals'));
    }
}
