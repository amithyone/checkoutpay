<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountNumber;
use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\MatchAttempt;
use App\Models\Payment;
use App\Models\ProcessedEmail;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Daily statistics
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        
        $dailyStats = [
            'amount_received' => Payment::where('status', Payment::STATUS_APPROVED)
                ->whereDate('created_at', $today)
                ->sum('received_amount') ?: Payment::where('status', Payment::STATUS_APPROVED)
                ->whereDate('created_at', $today)
                ->sum('amount'),
            'amount_received_yesterday' => Payment::where('status', Payment::STATUS_APPROVED)
                ->whereDate('created_at', $yesterday)
                ->sum('received_amount') ?: Payment::where('status', Payment::STATUS_APPROVED)
                ->whereDate('created_at', $yesterday)
                ->sum('amount'),
            'transactions_count' => Payment::whereDate('created_at', $today)->count(),
            'approved_count' => Payment::where('status', Payment::STATUS_APPROVED)
                ->whereDate('created_at', $today)
                ->count(),
            'pending_count' => Payment::where('status', Payment::STATUS_PENDING)
                ->whereDate('created_at', $today)
                ->count(),
        ];
        
        // Calculate percentage change
        $dailyStats['amount_change_percent'] = $dailyStats['amount_received_yesterday'] > 0
            ? round((($dailyStats['amount_received'] - $dailyStats['amount_received_yesterday']) / $dailyStats['amount_received_yesterday']) * 100, 2)
            : 0;
        
        // Statistics
        $stats = [
            'daily' => $dailyStats,
            'payments' => [
                'total' => Payment::count(),
                'pending' => Payment::where('status', Payment::STATUS_PENDING)->count(),
                'approved' => Payment::where('status', Payment::STATUS_APPROVED)->count(),
                'rejected' => Payment::where('status', Payment::STATUS_REJECTED)->count(),
                'total_amount' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->sum('received_amount') ?: Payment::where('status', Payment::STATUS_APPROVED)
                    ->sum('amount'),
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
                'total' => AccountNumber::active()->count(), // Only count active account numbers
                'pool' => AccountNumber::pool()->active()->count(),
                'business_specific' => AccountNumber::businessSpecific()->active()->count(),
                'total_payments_received_count' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->whereNotNull('account_number')
                    ->count(),
                'total_payments_received_amount' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->whereNotNull('account_number')
                    ->sum(DB::raw('COALESCE(received_amount, amount)')),
            ],
            'stored_emails' => [
                'total' => ProcessedEmail::count(),
                'matched' => ProcessedEmail::where('is_matched', true)->count(),
                'unmatched' => ProcessedEmail::where('is_matched', false)->count(),
                'imap' => ProcessedEmail::where('source', 'imap')->count(),
                'gmail_api' => ProcessedEmail::where('source', 'gmail_api')->count(),
                'direct_filesystem' => ProcessedEmail::where('source', 'direct_filesystem')->count(),
            ],
            'match_similarity' => [
                'total_score' => MatchAttempt::whereNotNull('name_similarity_percent')->sum('name_similarity_percent'),
                'total_attempts' => MatchAttempt::whereNotNull('name_similarity_percent')->count(),
                'average_score' => MatchAttempt::whereNotNull('name_similarity_percent')->avg('name_similarity_percent') ?: 0,
            ],
            'matching_time' => [
                'average_minutes' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('status', '!=', Payment::STATUS_PENDING) // Explicitly exclude pending
                    ->whereNotNull('matched_at')
                    ->get()
                    ->map(function ($payment) {
                        return $payment->created_at->diffInMinutes($payment->matched_at);
                    })
                    ->avg() ?: 0,
                'average_minutes_today' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('status', '!=', Payment::STATUS_PENDING) // Explicitly exclude pending
                    ->whereNotNull('matched_at')
                    ->whereDate('created_at', $today)
                    ->get()
                    ->map(function ($payment) {
                        return $payment->created_at->diffInMinutes($payment->matched_at);
                    })
                    ->avg() ?: 0,
                'total_matched' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('status', '!=', Payment::STATUS_PENDING) // Explicitly exclude pending
                    ->whereNotNull('matched_at')
                    ->count(),
                'total_matched_today' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->where('status', '!=', Payment::STATUS_PENDING) // Explicitly exclude pending
                    ->whereNotNull('matched_at')
                    ->whereDate('created_at', $today)
                    ->count(),
            ],
            'charges' => [
                'total_collected' => BusinessWebsite::sum('total_charges_collected'),
                'today_collected' => Payment::where('status', Payment::STATUS_APPROVED)
                    ->whereDate('created_at', $today)
                    ->whereNotNull('total_charges')
                    ->sum('total_charges'),
                'websites_with_charges_enabled' => BusinessWebsite::where('charges_enabled', true)->count(),
                'websites_with_charges_disabled' => BusinessWebsite::where('charges_enabled', false)->count(),
            ],
            'unmatched_pending' => [
                // Get pending payments that haven't been matched and are not expired
                // A payment is considered unmatched if status is pending and no processed_email has matched_payment_id = payment.id
                'total' => Payment::where('status', Payment::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('processed_emails')
                            ->whereColumn('processed_emails.matched_payment_id', 'payments.id')
                            ->where('processed_emails.is_matched', true);
                    })
                    ->count(),
                'expiring_soon' => Payment::where('status', Payment::STATUS_PENDING)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '>', now())
                    ->where('expires_at', '<=', now()->addHours(2)) // Expiring in next 2 hours
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('processed_emails')
                            ->whereColumn('processed_emails.matched_payment_id', 'payments.id')
                            ->where('processed_emails.is_matched', true);
                    })
                    ->count(),
                'recent' => Payment::where('status', Payment::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('processed_emails')
                            ->whereColumn('processed_emails.matched_payment_id', 'payments.id')
                            ->where('processed_emails.is_matched', true);
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

    /**
     * Trigger extract missing names cron job
     */
    public function extractMissingNames(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 50);
            
            // TODO: Command 'payment:extract-missing-names' does not exist
            // Using alternative: ReExtractFromTextBody command which extracts missing sender names
            \Illuminate\Support\Facades\Artisan::call('payment:re-extract-text-body', [
                '--limit' => $limit,
            ]);
            
            $output = \Illuminate\Support\Facades\Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Name extraction completed successfully',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error running extract missing names', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear all sender names and re-extract them to test accuracy
     */
    public function testSenderExtraction(Request $request)
    {
        try {
            $matchingService = new \App\Services\PaymentMatchingService();
            
            // Get initial stats
            $totalEmails = ProcessedEmail::count();
            $emailsWithSenderName = ProcessedEmail::whereNotNull('sender_name')->count();
            
            // Clear all sender names
            $cleared = ProcessedEmail::query()->update(['sender_name' => null]);
            
            // Re-extract sender names
            $emails = ProcessedEmail::all();
            $successCount = 0;
            $failedCount = 0;
            $skippedCount = 0;
            
            foreach ($emails as $email) {
                try {
                    $extracted = $matchingService->extractMissingFromTextBody($email);
                    if ($extracted && $email->refresh()->sender_name) {
                        $successCount++;
                    } else {
                        $skippedCount++;
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    \Illuminate\Support\Facades\Log::error('Error extracting sender name', [
                        'email_id' => $email->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Get final stats
            $newCount = ProcessedEmail::whereNotNull('sender_name')->count();
            $accuracy = $totalEmails > 0 ? round(($newCount / $totalEmails) * 100, 2) : 0;
            
            // Get sample extracted names
            $samples = ProcessedEmail::whereNotNull('sender_name')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get(['id', 'sender_name', 'subject']);
            
            return response()->json([
                'success' => true,
                'message' => 'Sender name extraction test completed',
                'results' => [
                    'total_emails' => $totalEmails,
                    'cleared' => $cleared,
                    'successfully_extracted' => $successCount,
                    'skipped' => $skippedCount,
                    'failed' => $failedCount,
                    'emails_with_sender_name_after' => $newCount,
                    'accuracy_percent' => $accuracy,
                    'before_extraction' => $emailsWithSenderName,
                ],
                'samples' => $samples->map(function ($email) {
                    return [
                        'id' => $email->id,
                        'sender_name' => $email->sender_name,
                        'subject' => substr($email->subject ?? 'N/A', 0, 60),
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error testing sender extraction', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
