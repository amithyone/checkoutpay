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
                'imap' => ProcessedEmail::where('source', 'imap')->count(),
                'gmail_api' => ProcessedEmail::where('source', 'gmail_api')->count(),
                'direct_filesystem' => ProcessedEmail::where('source', 'direct_filesystem')->count(),
            ],
            'unmatched_pending' => [
                // Get pending payments that haven't been matched and are not expired
                // A payment is considered unmatched if status is pending and no processed_email has matched_payment_id = payment.id
                'total' => Payment::where('status', Payment::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->whereNotIn('id', function ($query) {
                        $query->select('matched_payment_id')
                            ->from('processed_emails')
                            ->where('is_matched', true)
                            ->whereNotNull('matched_payment_id');
                    })
                    ->count(),
                'expiring_soon' => Payment::where('status', Payment::STATUS_PENDING)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>', now())
                    ->where('expires_at', '<=', now()->addHours(2)) // Expiring in next 2 hours
                    ->whereNotIn('id', function ($query) {
                        $query->select('matched_payment_id')
                            ->from('processed_emails')
                            ->where('is_matched', true)
                            ->whereNotNull('matched_payment_id');
                    })
                    ->count(),
                'recent' => Payment::where('status', Payment::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->whereNotIn('id', function ($query) {
                        $query->select('matched_payment_id')
                            ->from('processed_emails')
                            ->where('is_matched', true)
                            ->whereNotNull('matched_payment_id');
                    })
                    ->with('business')
                    ->latest()
                    ->limit(10)
                    ->get(),
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
