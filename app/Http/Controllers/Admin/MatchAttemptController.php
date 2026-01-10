<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchAttempt;
use App\Models\Payment;
use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MatchAttemptController extends Controller
{
    /**
     * Display match attempts index page
     */
    public function index(Request $request)
    {
        $query = MatchAttempt::with('payment', 'processedEmail')
            ->latest();

        // Filter by match result
        if ($request->filled('result')) {
            $query->where('match_result', $request->result);
        }

        // Filter by transaction ID
        if ($request->filled('transaction_id')) {
            $query->where('transaction_id', 'LIKE', '%' . $request->transaction_id . '%');
        }

        // Filter by extraction method
        if ($request->filled('extraction_method')) {
            $query->where('extraction_method', $request->extraction_method);
        }

        // Filter by processed email ID
        if ($request->filled('processed_email_id')) {
            $query->where('processed_email_id', $request->processed_email_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by reason (search)
        if ($request->filled('reason_search')) {
            $query->where('reason', 'LIKE', '%' . $request->reason_search . '%');
        }

        $attempts = $query->paginate(50)->withQueryString();

        // Statistics
        $stats = [
            'total' => MatchAttempt::count(),
            'matched' => MatchAttempt::where('match_result', 'matched')->count(),
            'unmatched' => MatchAttempt::where('match_result', 'unmatched')->count(),
            'today' => MatchAttempt::whereDate('created_at', today())->count(),
        ];

        // Common failure reasons
        $commonReasons = MatchAttempt::where('match_result', 'unmatched')
            ->selectRaw('LEFT(reason, 150) as reason_short, COUNT(*) as count')
            ->groupBy('reason_short')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return view('admin.match-attempts.index', compact('attempts', 'stats', 'commonReasons'));
    }

    /**
     * Show single match attempt details
     */
    public function show(MatchAttempt $matchAttempt)
    {
        $matchAttempt->load('payment', 'processedEmail');

        return view('admin.match-attempts.show', compact('matchAttempt'));
    }

    /**
     * Retry matching for a specific attempt
     */
    public function retry(MatchAttempt $matchAttempt, PaymentMatchingService $matchingService)
    {
        try {
            // Get the payment and email data
            $matchAttempt->load('payment', 'processedEmail');

            if (!$matchAttempt->payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found for this match attempt',
                ], 404);
            }

            // Skip if payment is already matched or expired
            if ($matchAttempt->payment->status !== Payment::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment is already ' . $matchAttempt->payment->status,
                ], 400);
            }

            if ($matchAttempt->payment->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment has expired',
                ], 400);
            }

            // Rebuild email data from processed email or match attempt
            $emailData = null;
            if ($matchAttempt->processedEmail) {
                $emailData = [
                    'subject' => $matchAttempt->processedEmail->subject,
                    'from' => $matchAttempt->processedEmail->from_email,
                    'text' => $matchAttempt->processedEmail->text_body ?? '',
                    'html' => $matchAttempt->processedEmail->html_body ?? '',
                    'date' => $matchAttempt->processedEmail->email_date ? $matchAttempt->processedEmail->email_date->toDateTimeString() : null,
                    'email_account_id' => $matchAttempt->processedEmail->email_account_id,
                    'processed_email_id' => $matchAttempt->processed_email_id,
                ];
            } else {
                // Use data from match attempt if no processed email
                $emailData = [
                    'subject' => $matchAttempt->email_subject,
                    'from' => $matchAttempt->email_from,
                    'text' => $matchAttempt->text_snippet ?? '',
                    'html' => $matchAttempt->html_snippet ?? '',
                    'date' => $matchAttempt->email_date ? $matchAttempt->email_date->toDateTimeString() : null,
                    'email_account_id' => null,
                    'processed_email_id' => $matchAttempt->processed_email_id,
                ];
            }

            // Try to match again
            $matchedPayment = $matchingService->matchEmail($emailData);

            if ($matchedPayment && $matchedPayment->id === $matchAttempt->payment_id) {
                // Process the email payment (approve payment)
                \App\Jobs\ProcessEmailPayment::dispatchSync($emailData);

                Log::info('Match attempt retry successful', [
                    'match_attempt_id' => $matchAttempt->id,
                    'transaction_id' => $matchAttempt->transaction_id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment matched and approved successfully!',
                    'payment' => $matchedPayment->fresh(),
                ]);
            } else {
                // Check for new match attempt in database
                $latestAttempt = MatchAttempt::where('payment_id', $matchAttempt->payment_id)
                    ->latest()
                    ->first();

                return response()->json([
                    'success' => false,
                    'message' => 'Payment still does not match. Check latest match attempt for details.',
                    'latest_reason' => $latestAttempt ? $latestAttempt->reason : 'No match attempt found',
                    'latest_attempt' => $latestAttempt ? [
                        'id' => $latestAttempt->id,
                        'reason' => $latestAttempt->reason,
                        'created_at' => $latestAttempt->created_at->format('Y-m-d H:i:s'),
                    ] : null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error retrying match attempt', [
                'match_attempt_id' => $matchAttempt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrying match: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry matching for a processed email
     */
    public function retryEmail(ProcessedEmail $processedEmail, PaymentMatchingService $matchingService)
    {
        try {
            // Rebuild email data
            $emailData = [
                'subject' => $processedEmail->subject,
                'from' => $processedEmail->from_email,
                'text' => $processedEmail->text_body ?? '',
                'html' => $processedEmail->html_body ?? '',
                'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : null,
                'email_account_id' => $processedEmail->email_account_id,
                'processed_email_id' => $processedEmail->id,
            ];

            // Try to match against all pending payments
            $matchedPayment = $matchingService->matchEmail($emailData);

            if ($matchedPayment) {
                // Process the email payment (approve payment)
                \App\Jobs\ProcessEmailPayment::dispatchSync($emailData);

                Log::info('Email match retry successful', [
                    'processed_email_id' => $processedEmail->id,
                    'transaction_id' => $matchedPayment->transaction_id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Email matched to payment and approved successfully!',
                    'payment' => $matchedPayment->fresh(),
                ]);
            } else {
                // Check latest match attempt for this email
                $latestAttempt = MatchAttempt::where('processed_email_id', $processedEmail->id)
                    ->latest()
                    ->first();

                return response()->json([
                    'success' => false,
                    'message' => 'Email does not match any pending payment. Check match attempts for details.',
                    'latest_reason' => $latestAttempt ? $latestAttempt->reason : 'No match attempt found',
                    'latest_attempt' => $latestAttempt ? [
                        'id' => $latestAttempt->id,
                        'transaction_id' => $latestAttempt->transaction_id,
                        'reason' => $latestAttempt->reason,
                        'created_at' => $latestAttempt->created_at->format('Y-m-d H:i:s'),
                    ] : null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error retrying email match', [
                'processed_email_id' => $processedEmail->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrying match: ' . $e->getMessage(),
            ], 500);
        }
    }
}
