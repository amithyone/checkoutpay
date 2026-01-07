<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountNumber;
use App\Models\Business;
use App\Models\Payment;
use App\Models\ProcessedEmail;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Statistics
        $stats = [
            'payments' => [
                'total' => Payment::count(),
                'pending' => Payment::where('status', Payment::STATUS_PENDING)->count(),
                'approved' => Payment::where('status', Payment::STATUS_APPROVED)->count(),
                'rejected' => Payment::where('status', Payment::STATUS_REJECTED)->count(),
            ],
            'businesses' => [
                'total' => Business::count(),
                'active' => Business::where('is_active', true)->count(),
            ],
            'withdrawals' => [
                'total' => WithdrawalRequest::count(),
                'pending' => WithdrawalRequest::where('status', WithdrawalRequest::STATUS_PENDING)->count(),
                'approved' => WithdrawalRequest::where('status', WithdrawalRequest::STATUS_APPROVED)->count(),
            ],
            'account_numbers' => [
                'total' => AccountNumber::count(),
                'pool' => AccountNumber::pool()->active()->count(),
                'business_specific' => AccountNumber::businessSpecific()->active()->count(),
            ],
            'stored_emails' => [
                'total' => ProcessedEmail::count(),
                'matched' => ProcessedEmail::where('is_matched', true)->count(),
                'unmatched' => ProcessedEmail::where('is_matched', false)->count(),
            ],
        ];

        // Recent payments
        $recentPayments = Payment::with('business')
            ->latest()
            ->limit(10)
            ->get();

        // Pending withdrawals
        $pendingWithdrawals = WithdrawalRequest::with('business')
            ->where('status', WithdrawalRequest::STATUS_PENDING)
            ->latest()
            ->limit(10)
            ->get();

        // Recent stored emails
        $recentStoredEmails = ProcessedEmail::with('emailAccount', 'matchedPayment')
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentPayments', 'pendingWithdrawals', 'recentStoredEmails'));
    }
}
