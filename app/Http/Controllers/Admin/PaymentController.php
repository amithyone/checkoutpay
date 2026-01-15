<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\MatchAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Payment::with(['business', 'website'])
            ->withCount(['matchAttempts', 'statusChecks'])
            ->latest();

        // If searching, show all payments regardless of expiration
        $isSearching = $request->filled('search');
        
        if ($request->filled('status')) {
            if ($request->status === 'pending') {
                // For pending, only show non-expired (unless searching)
                $query->where('status', Payment::STATUS_PENDING);
                if (!$isSearching) {
                    $query->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
                }
            } else {
                $query->where('status', $request->status);
            }
        } else {
            // Default: show all including expired (unless searching, then show everything)
            if (!$isSearching) {
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
            // If searching, don't apply default expiration filter - show all
        }

        // Filter for unmatched pending transactions
        if ($request->filled('unmatched') && $request->unmatched === '1') {
            $query->where('status', Payment::STATUS_PENDING);
            if (!$isSearching) {
                $query->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
            }
            $query->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('processed_emails')
                    ->whereColumn('processed_emails.matched_payment_id', 'payments.id')
                    ->where('processed_emails.is_matched', true);
            });
        }

        // Filter for transactions needing review (multiple API status checks)
        if ($request->filled('needs_review') && $request->needs_review === '1') {
            $query->where('status', Payment::STATUS_PENDING);
            if (!$isSearching) {
                $query->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
            }
            $query->withCount('statusChecks')
                ->having('status_checks_count', '>=', 3); // 3 or more API checks
        }

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date . ' 00:00:00');
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
        }

        // Search by transaction ID
        if ($request->filled('search')) {
            $search = trim($request->search);
            
            // Remove TXN- prefix if present for flexible searching
            $searchClean = str_ireplace('TXN-', '', $search);
            
            // Use case-insensitive search with LIKE
            // MySQL LIKE is case-insensitive by default for most collations
            $query->where(function ($q) use ($search, $searchClean) {
                // Search with original term (handles both with and without TXN-)
                $q->whereRaw('LOWER(transaction_id) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(transaction_id) LIKE ?', ['%' . strtolower($searchClean) . '%'])
                  ->orWhereRaw('LOWER(transaction_id) LIKE ?', ['%txn-' . strtolower($searchClean) . '%']);
            });
        }

        $payments = $query->paginate(20)->withQueryString();

        return view('admin.payments.index', compact('payments'));
    }

    public function show(Payment $payment): View
    {
        $payment->load([
            'business', 
            'accountNumberDetails',
            'matchAttempts' => function($q) {
                $q->latest()->limit(10);
            },
            'statusChecks' => function($q) {
                $q->latest()->limit(10);
            }
        ]);
        
        $statusChecksCount = \App\Models\PaymentStatusCheck::where('payment_id', $payment->id)
            ->where('payment_status', Payment::STATUS_PENDING)
            ->count();

        // Get unmatched emails that could be linked to this payment (same amount, same email account if applicable)
        $unmatchedEmails = \App\Models\ProcessedEmail::unmatched()
            ->where(function($q) use ($payment) {
                // Match by amount (within reasonable range)
                $q->where('amount', $payment->amount)
                  ->orWhereBetween('amount', [$payment->amount - 50, $payment->amount + 50]);
            })
            ->when($payment->business && $payment->business->email_account_id, function($q) use ($payment) {
                // Filter by email account if business has one assigned
                $q->where('email_account_id', $payment->business->email_account_id);
            })
            ->latest()
            ->limit(20)
            ->get();
            
        return view('admin.payments.show', compact('payment', 'statusChecksCount', 'unmatchedEmails'));
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

                $emailDate = $storedEmail->email_date ? \Carbon\Carbon::parse($storedEmail->email_date) : null;
                $match = $matchingService->matchPayment($payment, $extractedInfo, $emailDate);

                // Log match attempt
                try {
                    $matchLogger->logAttempt([
                        'payment_id' => $payment->id,
                        'processed_email_id' => $storedEmail->id,
                        'transaction_id' => $payment->transaction_id,
                        'match_result' => $match['matched'] ? MatchAttempt::RESULT_MATCHED : MatchAttempt::RESULT_UNMATCHED,
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
                        'sender_name' => $storedEmail->sender_name, // Map sender_name to payer_name
                    ]);
                    
                    // Update payer_account_number if extracted
                    if (isset($match['extracted_info']['payer_account_number']) && $match['extracted_info']['payer_account_number']) {
                        $payment->update(['payer_account_number' => $match['extracted_info']['payer_account_number']]);
                    }

                    // Update business balance
                    if ($payment->business_id) {
                        $payment->business->increment('balance', $payment->amount);
                        
                        // Send new deposit notification
                        $payment->business->notify(new \App\Notifications\NewDepositNotification($payment));
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

    /**
     * Manually approve a payment (even if it doesn't match)
     */
    public function manualApprove(Request $request, Payment $payment): RedirectResponse
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
            'received_amount' => 'nullable|numeric|min:0',
            'is_mismatch' => 'boolean',
            'email_id' => 'nullable|exists:processed_emails,id',
        ]);

        // Only allow manual approval for pending payments
        if ($payment->status !== Payment::STATUS_PENDING) {
            return redirect()->route('admin.payments.show', $payment)
                ->with('error', 'Payment is already ' . $payment->status);
        }

        $receivedAmount = $request->received_amount ?? $payment->amount;
        $isMismatch = $request->is_mismatch ?? ($receivedAmount != $payment->amount);

        // Get email data if email is linked
        $emailData = $payment->email_data ?? [];
        $linkedEmail = null;

        if ($request->email_id) {
            $linkedEmail = \App\Models\ProcessedEmail::find($request->email_id);
            if ($linkedEmail && !$linkedEmail->is_matched) {
                // Link email to payment
                $linkedEmail->markAsMatched($payment);

                // Extract payment info from email for webhook
                $matchingService = new \App\Services\PaymentMatchingService(
                    new \App\Services\TransactionLogService()
                );
                
                $emailDataForExtraction = [
                    'subject' => $linkedEmail->subject,
                    'from' => $linkedEmail->from_email,
                    'text' => $linkedEmail->text_body ?? '',
                    'html' => $linkedEmail->html_body ?? '',
                    'date' => $linkedEmail->email_date ? $linkedEmail->email_date->toDateTimeString() : null,
                ];

                $extractionResult = $matchingService->extractPaymentInfo($emailDataForExtraction);
                $extractedInfo = is_array($extractionResult) && isset($extractionResult['data']) 
                    ? $extractionResult['data'] 
                    : $extractionResult;

                // Build email data for approval
                $emailData = array_merge([
                    'subject' => $linkedEmail->subject,
                    'from' => $linkedEmail->from_email,
                    'text' => $linkedEmail->text_body ?? '',
                    'html' => $linkedEmail->html_body ?? '',
                    'date' => $linkedEmail->email_date ? $linkedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                    'payer_name' => $extractedInfo['sender_name'] ?? $payment->payer_name,
                    'bank' => $extractedInfo['bank'] ?? null,
                    'payer_account_number' => $extractedInfo['account_number'] ?? null,
                    'transaction_date' => $linkedEmail->email_date ? $linkedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                ], $extractedInfo ?? []);

            }
        }

        // Merge with manual approval metadata
        $emailData = array_merge($emailData, [
            'manual_approval' => true,
            'approved_by' => auth('admin')->user()->id,
            'approved_by_name' => auth('admin')->user()->name,
            'approved_at' => now()->toDateTimeString(),
            'admin_notes' => $request->admin_notes,
            'linked_email_id' => $linkedEmail?->id,
        ]);

        // Approve payment (this will update payer_name, bank, payer_account_number from email_data if provided)
        $payment->approve(
            emailData: $emailData,
            isMismatch: $isMismatch,
            receivedAmount: $receivedAmount,
            mismatchReason: $isMismatch ? ($request->admin_notes ?? 'Manual approval with amount mismatch') : null
        );

        // Update business balance
        if ($payment->business_id) {
            $payment->business->increment('balance', $receivedAmount);
        }

        // Log the manual approval
        \Illuminate\Support\Facades\Log::info('Payment manually approved by admin', [
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'admin_id' => auth('admin')->id(),
            'received_amount' => $receivedAmount,
            'expected_amount' => $payment->amount,
            'linked_email_id' => $linkedEmail?->id,
        ]);

        // Dispatch event to send webhook to business
        event(new \App\Events\PaymentApproved($payment));

        return redirect()->route('admin.payments.show', $payment)
            ->with('success', 'Payment manually approved, business credited, and webhook sent successfully.');
    }

    /**
     * Get unmatched emails for a payment (AJAX endpoint)
     */
    public function getUnmatchedEmails(Request $request, Payment $payment): \Illuminate\Http\JsonResponse
    {
        $amount = $request->query('amount', $payment->amount);
        
        $unmatchedEmails = \App\Models\ProcessedEmail::unmatched()
            ->where(function($q) use ($amount) {
                $q->where('amount', $amount)
                  ->orWhereBetween('amount', [$amount - 50, $amount + 50]);
            })
            ->when($payment->business && $payment->business->email_account_id, function($q) use ($payment) {
                $q->where('email_account_id', $payment->business->email_account_id);
            })
            ->latest()
            ->limit(10)
            ->get(['id', 'subject', 'from_email', 'amount', 'email_date']);

        return response()->json([
            'emails' => $unmatchedEmails->map(function($email) {
                return [
                    'id' => $email->id,
                    'subject' => $email->subject,
                    'from_email' => $email->from_email,
                    'amount' => $email->amount,
                    'email_date' => $email->email_date?->format('M d, Y H:i'),
                ];
            }),
        ]);
    }

    /**
     * Show transactions needing review (multiple API status checks by business)
     */
    public function needsReview(Request $request): View
    {
        // Get payments with 3+ API status checks by business that are still pending
        $query = Payment::with(['business', 'statusChecks' => function($q) {
                $q->latest()
                  ->limit(5);
            }])
            ->with('accountNumberDetails')
            ->where('status', Payment::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->withCount('statusChecks')
            ->having('status_checks_count', '>=', 3)
            ->latest();

        // Filter by business
        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date . ' 00:00:00');
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
        }

        $payments = $query->paginate(20)->withQueryString();

        return view('admin.payments.needs-review', compact('payments'));
    }

    /**
     * Mark a payment as expired (stops matching attempts)
     */
    public function markAsExpired(Payment $payment): RedirectResponse
    {
        if ($payment->status !== Payment::STATUS_PENDING) {
            return redirect()->route('admin.payments.show', $payment)
                ->with('error', 'Only pending payments can be marked as expired.');
        }

        // Set expires_at to now to stop matching attempts
        $payment->update([
            'expires_at' => now(),
        ]);

        // Log the action
        \Illuminate\Support\Facades\Log::info('Payment marked as expired by admin', [
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'admin_id' => auth('admin')->id(),
        ]);

        return redirect()->route('admin.payments.show', $payment)
            ->with('success', 'Payment marked as expired. Matching attempts will stop.');
    }

    /**
     * Resend webhook notification for an approved payment
     */
    public function resendWebhook(Payment $payment): \Illuminate\Http\JsonResponse
    {
        // Only allow resending for approved payments
        if ($payment->status !== Payment::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook can only be resent for approved payments.',
            ], 400);
        }

        // Check if webhook URL exists
        if (!$payment->webhook_url) {
            return response()->json([
                'success' => false,
                'message' => 'No webhook URL configured for this payment.',
            ], 400);
        }

        try {
            // Reload payment with relationships
            $payment->load(['business', 'accountNumberDetails', 'website']);

            // Create job instance and execute synchronously
            $job = new \App\Jobs\SendWebhookNotification($payment);
            $logService = app(\App\Services\TransactionLogService::class);
            $job->handle($logService);

            // Log the resend action
            \Illuminate\Support\Facades\Log::info('Webhook resent by admin - executed successfully', [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'admin_id' => auth('admin')->id(),
                'webhook_url' => $payment->webhook_url,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook notification has been sent successfully.',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error resending webhook', [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend webhook: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a payment
     */
    public function destroy(Payment $payment): RedirectResponse
    {
        $transactionId = $payment->transaction_id;

        // Soft delete the payment
        $payment->delete();

        // Log the deletion
        \Illuminate\Support\Facades\Log::info('Payment deleted by admin', [
            'payment_id' => $payment->id,
            'transaction_id' => $transactionId,
            'admin_id' => auth('admin')->id(),
        ]);

        return redirect()->route('admin.payments.index')
            ->with('success', "Payment {$transactionId} has been deleted successfully.");
    }
}
