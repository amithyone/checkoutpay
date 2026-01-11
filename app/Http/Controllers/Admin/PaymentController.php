<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Payment::with('business')->latest();

        if ($request->has('status')) {
            if ($request->status === 'pending') {
                // For pending, only show non-expired
                $query->where('status', Payment::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            } else {
                $query->where('status', $request->status);
            }
        } else {
            // Default: show all including expired
            $query->where(function ($q) {
                // Include all statuses, but for pending, exclude expired
                $q->where('status', '!=', Payment::STATUS_PENDING)
                    ->orWhere(function ($pendingQ) {
                        $pendingQ->where('status', Payment::STATUS_PENDING)
                            ->where(function ($expQ) {
                                $expQ->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            });
                    });
            });
        }

        // Filter for unmatched pending transactions
        if ($request->has('unmatched') && $request->unmatched === '1') {
            $query->where('status', Payment::STATUS_PENDING)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->whereNotExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('processed_emails')
                        ->whereColumn('processed_emails.matched_payment_id', 'payments.id')
                        ->where('processed_emails.is_matched', true);
                });
        }

        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $payments = $query->paginate(20);

        return view('admin.payments.index', compact('payments'));
    }

    public function show(Payment $payment): View
    {
        $payment->load('business', 'accountNumberDetails');
        return view('admin.payments.show', compact('payment'));
    }

    /**
     * Check match for a payment against stored emails
     */
    public function checkMatch(Payment $payment)
    {
        try {
            // Only check if payment is pending
            if ($payment->status !== Payment::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment is already ' . $payment->status,
                ], 400);
            }

            $matchingService = new \App\Services\PaymentMatchingService(
                new \App\Services\TransactionLogService()
            );
            $matchLogger = new \App\Services\MatchAttemptLogger();

            // Get unmatched stored emails with matching amount
            $storedEmails = \App\Models\ProcessedEmail::unmatched()
                ->withAmount($payment->amount)
                ->get();

            $matchedEmail = null;
            $matches = [];

            foreach ($storedEmails as $storedEmail) {
                // Re-extract payment info from html_body
                $emailData = [
                    'subject' => $storedEmail->subject,
                    'from' => $storedEmail->from_email,
                    'text' => $storedEmail->text_body ?? '',
                    'html' => $storedEmail->html_body ?? '',
                    'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : null,
                    'email_account_id' => $storedEmail->email_account_id,
                    'processed_email_id' => $storedEmail->id, // Pass ID for logging
                ];

                $extractionResult = $matchingService->extractPaymentInfo($emailData);
                
                // Handle new format: ['data' => [...], 'method' => '...']
                $extractedInfo = null;
                $extractionMethod = null;
                if (is_array($extractionResult) && isset($extractionResult['data'])) {
                    $extractedInfo = $extractionResult['data'];
                    $extractionMethod = $extractionResult['method'] ?? null;
                } else {
                    $extractedInfo = $extractionResult; // Old format fallback
                    $extractionMethod = 'unknown';
                }

                if (!$extractedInfo || !isset($extractedInfo['amount']) || !$extractedInfo['amount']) {
                    continue;
                }

                $emailDate = $storedEmail->email_date ? new \DateTime($storedEmail->email_date->toDateTimeString()) : null;
                $match = $matchingService->matchPayment($payment, $extractedInfo, $emailDate);

                // Log match attempt
                try {
                    $matchLogger->logAttempt([
                        'payment_id' => $payment->id,
                        'processed_email_id' => $storedEmail->id,
                        'transaction_id' => $payment->transaction_id,
                        'match_result' => $match['matched'] ? \App\Models\MatchAttempt::RESULT_MATCHED : \App\Models\MatchAttempt::RESULT_UNMATCHED,
                        'reason' => $match['reason'] ?? 'Unknown reason',
                        'payment_amount' => $payment->amount,
                        'payment_name' => $payment->payer_name,
                        'payment_account_number' => $payment->account_number,
                        'payment_created_at' => $payment->created_at,
                        'extracted_amount' => $extractedInfo['amount'] ?? null,
                        'extracted_name' => $extractedInfo['sender_name'] ?? null,
                        'extracted_account_number' => $extractedInfo['account_number'] ?? null,
                        'email_subject' => $storedEmail->subject,
                        'email_from' => $storedEmail->from_email,
                        'email_date' => $emailDate,
                        'amount_diff' => $match['amount_diff'] ?? null,
                        'name_similarity_percent' => $match['name_similarity_percent'] ?? null,
                        'time_diff_minutes' => $match['time_diff_minutes'] ?? null,
                        'extraction_method' => $extractionMethod,
                        'details' => [
                            'match_details' => $match,
                            'extracted_info' => $extractedInfo,
                            'payment_data' => [
                                'transaction_id' => $payment->transaction_id,
                                'amount' => $payment->amount,
                                'payer_name' => $payment->payer_name,
                                'account_number' => $payment->account_number,
                                'created_at' => $payment->created_at->toISOString(),
                            ],
                        ],
                        'html_snippet' => $matchLogger->extractHtmlSnippet($storedEmail->html_body ?? '', $extractedInfo['amount'] ?? null),
                        'text_snippet' => $matchLogger->extractTextSnippet($storedEmail->text_body ?? '', $extractedInfo['amount'] ?? null),
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to log match attempt in checkMatch', [
                        'error' => $e->getMessage(),
                        'payment_id' => $payment->id,
                        'email_id' => $storedEmail->id,
                    ]);
                }

                $matches[] = [
                    'email_id' => $storedEmail->id,
                    'email_subject' => $storedEmail->subject,
                    'email_from' => $storedEmail->from_email,
                    'matched' => $match['matched'],
                    'reason' => $match['reason'],
                    'time_diff_minutes' => $storedEmail->email_date && $payment->created_at 
                        ? abs($storedEmail->email_date->diffInMinutes($payment->created_at))
                        : null,
                ];

                if ($match['matched']) {
                    $matchedEmail = $storedEmail;

                    // Mark email as matched
                    $storedEmail->markAsMatched($payment);

                    // Approve payment
                    $payment->approve([
                        'subject' => $storedEmail->subject,
                        'from' => $storedEmail->from_email,
                        'text' => $storedEmail->text_body,
                        'html' => $storedEmail->html_body,
                        'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                    ]);
                    
                    // Update payer_account_number if extracted
                    if (isset($match['extracted_info']['payer_account_number']) && $match['extracted_info']['payer_account_number']) {
                        $payment->update(['payer_account_number' => $match['extracted_info']['payer_account_number']]);
                    }

                    // Update business balance
                    if ($payment->business_id) {
                        $payment->business->increment('balance', $payment->amount);
                    }

                    // Dispatch event to send webhook
                    event(new \App\Events\PaymentApproved($payment));

                    break;
                }
            }

            return response()->json([
                'success' => true,
                'matched' => $matchedEmail !== null,
                'payment' => [
                    'id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                ],
                'email' => $matchedEmail ? [
                    'id' => $matchedEmail->id,
                    'subject' => $matchedEmail->subject,
                    'from_email' => $matchedEmail->from_email,
                ] : null,
                'matches' => $matches,
                'message' => $matchedEmail 
                    ? 'Payment matched and approved successfully!' 
                    : 'No matching email found. Check the matches below for details.',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in checkMatch for payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error checking match: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
