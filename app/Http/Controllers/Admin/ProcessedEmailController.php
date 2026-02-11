<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessedEmail;
use Illuminate\Http\Request;

class ProcessedEmailController extends Controller
{
    /**
     * Display inbox of stored emails
     */
    public function index(Request $request)
    {
        $query = ProcessedEmail::with('emailAccount', 'matchedPayment')
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'matched') {
                $query->where('is_matched', true);
            } elseif ($request->status === 'unmatched') {
                $query->where('is_matched', false);
            }
        }

        // Filter by email account
        if ($request->filled('email_account_id')) {
            $query->where('email_account_id', $request->email_account_id);
        }

        // Search by Subject, From, Amount, Sender, and Transaction ID
        if ($request->filled('search')) {
            $search = trim($request->search);
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    // Search in subject
                    $q->where('subject', 'like', "%{$search}%")
                        // Search in from email
                        ->orWhere('from_email', 'like', "%{$search}%")
                        // Search in from name
                        ->orWhere('from_name', 'like', "%{$search}%")
                        // Search in sender name
                        ->orWhere('sender_name', 'like', "%{$search}%");
                    
                    // Search by transaction ID (TXN- prefix or without)
                    // Remove TXN- prefix if present for flexible searching
                    $searchClean = str_ireplace('TXN-', '', $search);
                    
                    $q->orWhereHas('matchedPayment', function($paymentQuery) use ($search, $searchClean) {
                        $paymentQuery->where(function($pq) use ($search, $searchClean) {
                            // Use case-insensitive search with LOWER()
                            $pq->whereRaw('LOWER(transaction_id) LIKE ?', ['%' . strtolower($search) . '%'])
                              ->orWhereRaw('LOWER(transaction_id) LIKE ?', ['%' . strtolower($searchClean) . '%'])
                              ->orWhereRaw('LOWER(transaction_id) LIKE ?', ['%txn-' . strtolower($searchClean) . '%']);
                        });
                    });
                    
                    // If search looks like a number, also search in amount
                    $numericSearch = preg_replace('/[^0-9.]/', '', $search);
                    if (is_numeric($numericSearch) && $numericSearch > 0) {
                        $amount = (float) $numericSearch;
                        // Allow small tolerance for amount matching (handles formatting like 1000 vs 1000.00)
                        $q->orWhereBetween('amount', [$amount - 0.01, $amount + 0.01]);
                    }
                });
            }
        }

        $emails = $query->paginate(20)->withQueryString();

        // Statistics
        $stats = [
            'total' => ProcessedEmail::count(),
            'matched' => ProcessedEmail::where('is_matched', true)->count(),
            'unmatched' => ProcessedEmail::where('is_matched', false)->count(),
        ];

        // Get email accounts for filter
        $emailAccounts = \App\Models\EmailAccount::all();

        return view('admin.processed-emails.index', compact('emails', 'stats', 'emailAccounts'));
    }

    /**
     * Show email details
     */
    public function show(ProcessedEmail $processedEmail)
    {
        $processedEmail->load('emailAccount', 'matchedPayment.business');
        
        return view('admin.processed-emails.show', compact('processedEmail'));
    }

    /**
     * Check match for a stored email against pending payments
     */
    public function checkMatch(ProcessedEmail $processedEmail)
    {
        try {
            // Use same logic as PaymentController::checkMatch (which works!)
            $matchingService = new \App\Services\PaymentMatchingService(
                new \App\Services\TransactionLogService()
            );
            $matchLogger = new \App\Services\MatchAttemptLogger();
            
            // Skip emails without amount (can't match without amount)
            if (!$processedEmail->amount || $processedEmail->amount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email does not have an amount. Cannot match without amount.',
                ], 400);
            }
            
            // Get unmatched stored emails with matching amount (same as PaymentController::checkMatch)
            $storedEmails = \App\Models\ProcessedEmail::where('is_matched', false)
                ->where('id', $processedEmail->id) // Only check this specific email
                ->whereBetween('amount', [
                    $processedEmail->amount - 1,
                    $processedEmail->amount + 1
                ])
                ->get();

            $matchedPayment = null;
            $matches = [];

            foreach ($storedEmails as $storedEmail) {
                // Re-extract payment info from html_body (same as PaymentController::checkMatch)
                $emailData = [
                    'subject' => $storedEmail->subject,
                    'from' => $storedEmail->from_email,
                    'text' => $storedEmail->text_body ?? '',
                    'html' => $storedEmail->html_body ?? '',
                    'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : null,
                    'email_account_id' => $storedEmail->email_account_id,
                    'processed_email_id' => $storedEmail->id,
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

                // Use stored values as fallback if extraction fails (same as PaymentController::checkMatch)
                if (!$extractedInfo || !isset($extractedInfo['amount']) || !$extractedInfo['amount']) {
                    $extractedInfo = [
                        'amount' => $storedEmail->amount,
                        'sender_name' => $storedEmail->sender_name,
                        'account_number' => $storedEmail->account_number,
                    ];
                } else {
                    // Merge stored values if extraction didn't provide them
                    if (!isset($extractedInfo['amount']) && $storedEmail->amount) {
                        $extractedInfo['amount'] = $storedEmail->amount;
                    }
                    if (!isset($extractedInfo['sender_name']) && $storedEmail->sender_name) {
                        $extractedInfo['sender_name'] = $storedEmail->sender_name;
                    }
                    if (!isset($extractedInfo['account_number']) && $storedEmail->account_number) {
                        $extractedInfo['account_number'] = $storedEmail->account_number;
                    }
                }

                // Find payments with matching amount (same as PaymentController::checkMatch)
                $emailDate = $storedEmail->email_date ? \Carbon\Carbon::parse($storedEmail->email_date) : null;
                
                $potentialPayments = \App\Models\Payment::where('status', \App\Models\Payment::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->whereBetween('amount', [
                        $extractedInfo['amount'] - 1,
                        $extractedInfo['amount'] + 1
                    ])
                    ->where('created_at', '<=', $emailDate ?? now()) // Payment must be created BEFORE email
                    ->orderBy('created_at', 'desc')
                    ->get();

                foreach ($potentialPayments as $payment) {
                    $match = $matchingService->matchPayment($payment, $extractedInfo, $emailDate);

                    // Log match attempt (same as PaymentController::checkMatch)
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
                        ]);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to log match attempt in inbox checkMatch', [
                            'error' => $e->getMessage(),
                            'payment_id' => $payment->id,
                            'email_id' => $storedEmail->id,
                        ]);
                    }

                    $matches[] = [
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'matched' => $match['matched'],
                        'reason' => $match['reason'],
                    ];

                    if ($match['matched']) {
                        $matchedPayment = $payment;

                        // Mark email as matched (same as PaymentController::checkMatch)
                        $storedEmail->markAsMatched($payment);

                        // Approve payment (same as PaymentController::checkMatch)
                        $payment->approve([
                            'subject' => $storedEmail->subject,
                            'from' => $storedEmail->from_email,
                            'text' => $storedEmail->text_body,
                            'html' => $storedEmail->html_body,
                            'date' => $storedEmail->email_date ? $storedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                            'sender_name' => $storedEmail->sender_name,
                        ]);
                        
                        // Update payer_account_number if extracted (same as PaymentController::checkMatch)
                        if (isset($extractedInfo['payer_account_number']) && $extractedInfo['payer_account_number']) {
                            $payment->update(['payer_account_number' => $extractedInfo['payer_account_number']]);
                        }

                        // Update business balance (same as PaymentController::checkMatch)
                        if ($payment->business_id) {
                            $payment->business->incrementBalanceWithCharges($payment->amount, $payment);
                            $payment->business->refresh(); // Refresh to get updated balance
                            
                            // Send new deposit notification
                            $payment->business->notify(new \App\Notifications\NewDepositNotification($payment));
                            
                            // Check for auto-withdrawal
                            $payment->business->triggerAutoWithdrawal();
                        }

                        // CRITICAL: Reload payment with business websites relationship before dispatching webhook
                        $payment->refresh();
                        $payment->load(['business.websites', 'website']);

                        // Dispatch event to send webhook to ALL websites under the business
                        event(new \App\Events\PaymentApproved($payment));

                        break;
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'matched' => $matchedPayment !== null,
                'payment' => $matchedPayment ? [
                    'id' => $matchedPayment->id,
                    'transaction_id' => $matchedPayment->transaction_id,
                    'amount' => $matchedPayment->amount,
                    'status' => $matchedPayment->status,
                ] : null,
                'email' => $matchedPayment ? [
                    'id' => $processedEmail->id,
                    'subject' => $processedEmail->subject,
                    'from_email' => $processedEmail->from_email,
                ] : null,
                'matches' => $matches,
                'message' => $matchedPayment 
                    ? 'Payment matched and approved successfully!' 
                    : 'No matching payment found. Check the matches below for details.',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error in checkMatch', [
                'email_id' => $processedEmail->id,
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
     * Update sender name for a processed email
     */
    public function updateName(Request $request, ProcessedEmail $processedEmail)
    {
        $request->validate([
            'sender_name' => 'required|string|max:255',
        ]);

        try {
            $senderName = strtolower(trim($request->sender_name));
            
            // Get current extracted_data or initialize empty array
            $extractedData = $processedEmail->extracted_data ?? [];
            
            // Update sender_name in extracted_data
            $extractedData['sender_name'] = $senderName;
            
            // Also update if it's nested in a 'data' key (some extraction methods use this structure)
            if (isset($extractedData['data']) && is_array($extractedData['data'])) {
                $extractedData['data']['sender_name'] = $senderName;
            }
            
            $processedEmail->update([
                'sender_name' => $senderName,
                'extracted_data' => $extractedData,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sender name and extracted data updated successfully',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating sender name', [
                'email_id' => $processedEmail->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating sender name: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update sender name and rematch the email
     */
    public function updateAndRematch(Request $request, ProcessedEmail $processedEmail)
    {
        $request->validate([
            'sender_name' => 'nullable|string|max:255',
        ]);

        try {
            $senderName = !empty($request->sender_name) ? strtolower(trim($request->sender_name)) : null;
            
            // SECONDARY TRY: If sender_name is empty or not provided, try extracting from text snippet (first 500 chars)
            if (empty($senderName) && !empty($processedEmail->text_body)) {
                $textSnippet = mb_substr($processedEmail->text_body, 0, 500);
                $nameExtractor = new \App\Services\SenderNameExtractor();
                $extractedName = $nameExtractor->extractFromText($textSnippet, $processedEmail->subject ?? '');
                
                if (!empty($extractedName)) {
                    $senderName = $extractedName;
                    \Illuminate\Support\Facades\Log::info('Extracted sender name from text snippet on rematch', [
                        'email_id' => $processedEmail->id,
                        'extracted_name' => $extractedName,
                    ]);
                }
            }
            
            // If still no sender name, use existing one from email or proceed without it
            if (empty($senderName) && !empty($processedEmail->sender_name)) {
                $senderName = $processedEmail->sender_name;
            }
            
            // Get current extracted_data or initialize empty array
            $extractedData = $processedEmail->extracted_data ?? [];
            
            // Update sender_name in extracted_data if we have one
            if (!empty($senderName)) {
                $extractedData['sender_name'] = $senderName;
                
                // Also update if it's nested in a 'data' key (some extraction methods use this structure)
                if (isset($extractedData['data']) && is_array($extractedData['data'])) {
                    $extractedData['data']['sender_name'] = $senderName;
                }
            }
            
            // Update the sender name and extracted_data (only if we have a sender name)
            $updateData = ['extracted_data' => $extractedData];
            if (!empty($senderName)) {
                $updateData['sender_name'] = $senderName;
            }
            $processedEmail->update($updateData);

            // Refresh to get updated data
            $processedEmail->refresh();

            // Now rematch using the matching service
            $matchingService = new \App\Services\PaymentMatchingService(
                new \App\Services\TransactionLogService()
            );
            
            // Build email data array for matchEmail
            $emailData = [
                'subject' => $processedEmail->subject,
                'from' => $processedEmail->from_email,
                'text' => $processedEmail->text_body ?? '',
                'html' => $processedEmail->html_body ?? '',
                'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : null,
                'email_account_id' => $processedEmail->email_account_id,
                'processed_email_id' => $processedEmail->id,
            ];
            
            // Try to match email against pending payments
            $matchedPayment = $matchingService->matchEmail($emailData);
            
            // If a match is found, approve the payment
            if ($matchedPayment) {
                // Mark email as matched
                $processedEmail->markAsMatched($matchedPayment);
                
                // Approve payment
                $matchedPayment->approve([
                    'subject' => $processedEmail->subject,
                    'from' => $processedEmail->from_email,
                    'text' => $processedEmail->text_body,
                    'html' => $processedEmail->html_body,
                    'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                    'sender_name' => $processedEmail->sender_name, // Map sender_name to payer_name
                ]);
                
                // Update business balance
                if ($matchedPayment->business_id) {
                    $matchedPayment->business->incrementBalanceWithCharges($matchedPayment->amount, $matchedPayment);
                    $matchedPayment->business->refresh(); // Refresh to get updated balance
                    
                    // Check for auto-withdrawal
                    $matchedPayment->business->triggerAutoWithdrawal();
                }
                
                // CRITICAL: Reload payment with business websites relationship before dispatching webhook
                $matchedPayment->refresh();
                $matchedPayment->load(['business.websites', 'website']);
                
                // Dispatch event to send webhook to ALL websites under the business
                event(new \App\Events\PaymentApproved($matchedPayment));
            }

            // Get latest match reason if no match found (from match attempts)
            $latestReason = null;
            if (!$matchedPayment) {
                $latestAttempt = \App\Models\MatchAttempt::where('processed_email_id', $processedEmail->id)
                    ->latest()
                    ->first();
                if ($latestAttempt && $latestAttempt->reason) {
                    $latestReason = $latestAttempt->reason;
                }
            }
            
            return response()->json([
                'success' => true,
                'matched' => $matchedPayment !== null,
                'payment' => $matchedPayment ? [
                    'id' => $matchedPayment->id,
                    'transaction_id' => $matchedPayment->transaction_id,
                    'amount' => $matchedPayment->amount,
                    'status' => $matchedPayment->status, // Include status so frontend can refresh
                ] : null,
                'message' => $matchedPayment 
                    ? 'Sender name updated and payment matched successfully!' 
                    : 'Sender name updated. No matching payment found.',
                'latest_reason' => $latestReason,
                'redirect_url' => $matchedPayment ? route('admin.payments.show', $matchedPayment) : null,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating and rematching', [
                'email_id' => $processedEmail->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating and rematching: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update amount for a processed email
     */
    public function updateAmount(Request $request, ProcessedEmail $processedEmail)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $amount = (float) $request->amount;
            
            // Get current extracted_data or initialize empty array
            $extractedData = $processedEmail->extracted_data ?? [];
            
            // Update amount in extracted_data
            $extractedData['amount'] = $amount;
            
            // Also update if it's nested in a 'data' key (some extraction methods use this structure)
            if (isset($extractedData['data']) && is_array($extractedData['data'])) {
                $extractedData['data']['amount'] = $amount;
            }
            
            $processedEmail->update([
                'amount' => $amount,
                'extracted_data' => $extractedData,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Amount and extracted data updated successfully',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating amount', [
                'email_id' => $processedEmail->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating amount: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update amount and rematch the email
     */
    public function updateAmountAndRematch(Request $request, ProcessedEmail $processedEmail)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $amount = (float) $request->amount;
            
            // Get current extracted_data or initialize empty array
            $extractedData = $processedEmail->extracted_data ?? [];
            
            // Update amount in extracted_data
            $extractedData['amount'] = $amount;
            
            // Also update if it's nested in a 'data' key (some extraction methods use this structure)
            if (isset($extractedData['data']) && is_array($extractedData['data'])) {
                $extractedData['data']['amount'] = $amount;
            }
            
            // Update the amount and extracted_data
            $processedEmail->update([
                'amount' => $amount,
                'extracted_data' => $extractedData,
            ]);

            // Refresh to get updated data
            $processedEmail->refresh();

            // Now rematch using the matching service
            $matchingService = new \App\Services\PaymentMatchingService(
                new \App\Services\TransactionLogService()
            );
            
            // Build email data array for matchEmail
            $emailData = [
                'subject' => $processedEmail->subject,
                'from' => $processedEmail->from_email,
                'text' => $processedEmail->text_body ?? '',
                'html' => $processedEmail->html_body ?? '',
                'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : null,
                'email_account_id' => $processedEmail->email_account_id,
                'processed_email_id' => $processedEmail->id,
            ];
            
            // Try to match email against pending payments
            $matchedPayment = $matchingService->matchEmail($emailData);
            
            // If a match is found, approve the payment
            if ($matchedPayment) {
                // Mark email as matched
                $processedEmail->markAsMatched($matchedPayment);
                
                // Get match result details (including received_amount for mismatches)
                $matchResult = $matchedPayment->getAttribute('_match_result');
                $isMismatch = $matchResult['is_mismatch'] ?? false;
                $receivedAmount = $matchResult['received_amount'] ?? null;
                $mismatchReason = $matchResult['mismatch_reason'] ?? null;
                
                // If edited amount differs from payment amount, use edited amount as received_amount
                if (!$receivedAmount && abs($amount - $matchedPayment->amount) > 0.01) {
                    $receivedAmount = $amount;
                    $isMismatch = true;
                    $mismatchReason = 'Amount manually edited. Expected: ₦' . number_format($matchedPayment->amount, 2) . ', Received: ₦' . number_format($amount, 2);
                }
                
                // Approve payment with mismatch info if applicable
                $matchedPayment->approve([
                    'subject' => $processedEmail->subject,
                    'from' => $processedEmail->from_email,
                    'text' => $processedEmail->text_body,
                    'html' => $processedEmail->html_body,
                    'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                    'sender_name' => $processedEmail->sender_name, // Map sender_name to payer_name
                    'amount' => $amount, // Use edited amount
                    'received_amount' => $receivedAmount ?? $amount, // Include received amount
                ], $isMismatch, $receivedAmount ?? $amount, $mismatchReason);
                
                // Update business balance - use received amount if mismatch, otherwise edited amount
                if ($matchedPayment->business_id) {
                    $balanceAmount = $receivedAmount ?? $amount;
                    $matchedPayment->business->incrementBalanceWithCharges($matchedPayment->amount, $matchedPayment, $balanceAmount);
                    $matchedPayment->business->refresh(); // Refresh to get updated balance
                    
                    // Check for auto-withdrawal
                    $matchedPayment->business->triggerAutoWithdrawal();
                }
                
                // CRITICAL: Reload payment with business websites relationship before dispatching webhook
                $matchedPayment->refresh();
                $matchedPayment->load(['business.websites', 'website']);
                
                // Dispatch event to send webhook to ALL websites under the business
                event(new \App\Events\PaymentApproved($matchedPayment));
            }

            // Get latest match reason if no match found (from match attempts)
            $latestReason = null;
            if (!$matchedPayment) {
                $latestAttempt = \App\Models\MatchAttempt::where('processed_email_id', $processedEmail->id)
                    ->latest()
                    ->first();
                if ($latestAttempt && $latestAttempt->reason) {
                    $latestReason = $latestAttempt->reason;
                }
            }
            
            return response()->json([
                'success' => true,
                'matched' => $matchedPayment !== null,
                'payment' => $matchedPayment ? [
                    'id' => $matchedPayment->id,
                    'transaction_id' => $matchedPayment->transaction_id,
                    'amount' => $matchedPayment->amount,
                    'status' => $matchedPayment->status, // Include status so frontend can refresh
                ] : null,
                'message' => $matchedPayment 
                    ? 'Amount updated and payment matched successfully!' 
                    : 'Amount updated. No matching payment found.',
                'latest_reason' => $latestReason,
                'redirect_url' => $matchedPayment ? route('admin.payments.show', $matchedPayment) : null,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating amount and rematching', [
                'email_id' => $processedEmail->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating amount and rematching: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pending payments for quick-match dropdown (AJAX).
     * Similar to payment page's unmatched-emails but from email side: list pending payments with simple labels.
     */
    public function getPendingPayments(\Illuminate\Http\Request $request, ProcessedEmail $processedEmail): \Illuminate\Http\JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $emailAmount = $processedEmail->amount ? (float) $processedEmail->amount : null;

        $query = \App\Models\Payment::with('business')
            ->where('status', \App\Models\Payment::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where('created_at', '<=', $processedEmail->created_at); // Payment created before email was stored

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                    ->orWhere('payer_name', 'like', "%{$search}%")
                    ->orWhere('payer_account_number', 'like', "%{$search}%")
                    ->orWhereHas('business', function ($bq) use ($search) {
                        $bq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $payments = $query->orderBy('created_at', 'desc')
            ->limit(200)
            ->get(['id', 'transaction_id', 'payer_name', 'amount', 'created_at', 'business_id']);

        return response()->json([
            'payments' => $payments->map(function ($p) use ($emailAmount) {
                $diff = $emailAmount !== null && $p->amount
                    ? ($emailAmount - (float) $p->amount)
                    : null;
                $diffText = $diff !== null
                    ? ($diff > 0 ? '+' . number_format($diff, 2) : number_format($diff, 2))
                    : null;
                $businessName = $p->relationLoaded('business') && $p->business
                    ? $p->business->name
                    : '';
                return [
                    'id' => $p->id,
                    'transaction_id' => $p->transaction_id,
                    'payer_name' => $p->payer_name ?? '—',
                    'amount' => (float) $p->amount,
                    'amount_formatted' => '₦' . number_format($p->amount, 2),
                    'business_name' => $businessName,
                    'created_at' => $p->created_at->format('M d, Y H:i'),
                    'difference' => $diff,
                    'difference_text' => $diffText,
                ];
            }),
        ]);
    }

    /**
     * Match this email to a pending payment and approve the payment (same flow as manual approve from payment page).
     */
    public function matchToPayment(\Illuminate\Http\Request $request, ProcessedEmail $processedEmail): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'payment_id' => 'required|exists:payments,id',
        ]);

        if ($processedEmail->is_matched) {
            return redirect()->route('admin.processed-emails.show', $processedEmail)
                ->with('error', 'This email is already matched to a payment.');
        }

        $payment = \App\Models\Payment::with('business')->findOrFail($request->payment_id);

        if ($payment->status !== \App\Models\Payment::STATUS_PENDING) {
            return redirect()->route('admin.processed-emails.show', $processedEmail)
                ->with('error', 'That payment is not pending (already ' . $payment->status . ').');
        }

        $linkedEmail = $processedEmail;
        $linkedEmail->markAsMatched($payment);

        $matchingService = new \App\Services\PaymentMatchingService(new \App\Services\TransactionLogService());
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

        $receivedAmount = $linkedEmail->amount ? (float) $linkedEmail->amount : $payment->amount;
        $emailAmount = $receivedAmount != $payment->amount ? $receivedAmount : ($extractedInfo['amount'] ?? $payment->amount);
        $isMismatch = abs($receivedAmount - $payment->amount) > 0.01;

        $emailData = [
            'subject' => $linkedEmail->subject,
            'from' => $linkedEmail->from_email,
            'date' => $linkedEmail->email_date ? $linkedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
            'sender_name' => $extractedInfo['sender_name'] ?? $payment->payer_name,
            'amount' => $emailAmount,
            'received_amount' => $receivedAmount,
            'processed_email_id' => $linkedEmail->id,
            'manual_approval' => true,
            'approved_by' => auth('admin')->user()->id,
            'approved_by_name' => auth('admin')->user()->name,
            'approved_at' => now()->toDateTimeString(),
            'admin_notes' => 'Quick match from inbox',
            'linked_email_id' => $linkedEmail->id,
        ];

        $payment->approve(
            emailData: $emailData,
            isMismatch: $isMismatch,
            receivedAmount: $receivedAmount,
            mismatchReason: $isMismatch ? 'Quick match from inbox – amount differs from expected' : null
        );

        if ($payment->business_id) {
            $payment->business->incrementBalanceWithCharges($payment->amount, $payment, $receivedAmount);
            $payment->business->refresh();
            $payment->business->triggerAutoWithdrawal();
        }

        \Illuminate\Support\Facades\Log::info('Payment quick-matched from inbox', [
            'payment_id' => $payment->id,
            'processed_email_id' => $linkedEmail->id,
            'admin_id' => auth('admin')->id(),
        ]);

        $payment->refresh();
        $payment->load(['business.websites', 'website']);
        event(new \App\Events\PaymentApproved($payment));

        return redirect()->route('admin.processed-emails.show', $processedEmail)
            ->with('success', 'Email matched and payment approved. Business credited and webhook sent.');
    }
}
