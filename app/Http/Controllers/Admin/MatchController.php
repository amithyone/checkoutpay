<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ProcessedEmail;
use App\Services\DescriptionFieldExtractor;
use App\Services\MatchAttemptLogger;
use App\Services\PaymentMatchingService;
use App\Services\TransactionLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatchController extends Controller
{
    /**
     * Trigger global match check for all unmatched pending payments and emails
     */
    public function triggerGlobalMatch(Request $request)
    {
        try {
            $matchingService = new PaymentMatchingService(
                new TransactionLogService()
            );
            $descriptionExtractor = new DescriptionFieldExtractor();

            $results = [
                'payments_checked' => 0,
                'emails_checked' => 0,
                'matches_found' => 0,
                'attempts_logged' => 0,
                'errors' => [],
                'matched_payments' => [],
                'matched_emails' => [],
            ];

            // Get all unmatched pending payments (not expired)
            $pendingPayments = Payment::where('status', Payment::STATUS_PENDING)
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
                ->get();

            // Get all unmatched processed emails
            $unmatchedEmails = ProcessedEmail::where('is_matched', false)
                ->latest()
                ->get();

            Log::info('Global match check triggered', [
                'pending_payments_count' => $pendingPayments->count(),
                'unmatched_emails_count' => $unmatchedEmails->count(),
            ]);

            // STEP 1: Extract missing sender_name and description_field from text_body
            // This runs ONLY before global match to fill in missing data
            $textBodyExtractedCount = 0;
            foreach ($unmatchedEmails as $processedEmail) {
                // Only extract if sender_name is null OR description_field is null
                if (!$processedEmail->sender_name || !$processedEmail->description_field) {
                    try {
                        $extracted = $matchingService->extractMissingFromTextBody($processedEmail);
                        if ($extracted) {
                            $textBodyExtractedCount++;
                            // Refresh the model to get updated data
                            $processedEmail->refresh();
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to extract from text_body in global match', [
                            'email_id' => $processedEmail->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            
            if ($textBodyExtractedCount > 0) {
                Log::info("Extracted {$textBodyExtractedCount} missing fields from text_body before matching");
            }
            
            // STEP 2: Parse description fields for emails that have them but missing account_number
            // This ensures account numbers are extracted before matching
            $parsedCount = 0;
            foreach ($unmatchedEmails as $processedEmail) {
                // Refresh to get latest data (might have been updated by text_body extraction)
                $processedEmail->refresh();
                
                if ($processedEmail->description_field && !$processedEmail->account_number) {
                    try {
                        $parsedData = $descriptionExtractor->parseDescriptionField($processedEmail->description_field);
                        if ($parsedData['account_number']) {
                            $currentExtractedData = $processedEmail->extracted_data ?? [];
                            $currentExtractedData['description_field'] = $processedEmail->description_field;
                            $currentExtractedData['account_number'] = $parsedData['account_number'];
                            $currentExtractedData['payer_account_number'] = $parsedData['payer_account_number'];
                            // SKIP amount_from_description - not reliable, use amount field instead
                            $currentExtractedData['date_from_description'] = $parsedData['extracted_date'];
                            
                            $processedEmail->update([
                                'account_number' => $parsedData['account_number'],
                                'extracted_data' => $currentExtractedData,
                            ]);
                            $parsedCount++;
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to parse description field in global match', [
                            'email_id' => $processedEmail->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            
            if ($parsedCount > 0) {
                Log::info("Parsed {$parsedCount} description fields before matching");
            }

            // Strategy: Use same logic as PaymentController::checkMatch (which works!)
            // For each unmatched email, extract info and match against pending payments
            $matchLogger = new \App\Services\MatchAttemptLogger();
            
            foreach ($unmatchedEmails as $processedEmail) {
                try {
                    // Skip if already matched (in case it was matched in this run)
                    $processedEmail->refresh();
                    if ($processedEmail->is_matched) {
                        continue;
                    }

                    // Skip emails without amount (can't match without amount)
                    if (!$processedEmail->amount || $processedEmail->amount <= 0) {
                        continue;
                    }

                    $results['emails_checked']++;

                    // Rebuild email data (same as PaymentController::checkMatch)
                    $emailData = [
                        'subject' => $processedEmail->subject,
                        'from' => $processedEmail->from_email,
                        'text' => $processedEmail->text_body ?? '',
                        'html' => $processedEmail->html_body ?? '',
                        'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : null,
                        'email_account_id' => $processedEmail->email_account_id,
                        'processed_email_id' => $processedEmail->id,
                    ];

                    // Extract payment info from email (same as PaymentController::checkMatch)
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
                            'amount' => $processedEmail->amount,
                            'sender_name' => $processedEmail->sender_name,
                            'account_number' => $processedEmail->account_number,
                        ];
                    } else {
                        // Merge stored values if extraction didn't provide them
                        if (!isset($extractedInfo['amount']) && $processedEmail->amount) {
                            $extractedInfo['amount'] = $processedEmail->amount;
                        }
                        if (!isset($extractedInfo['sender_name']) && $processedEmail->sender_name) {
                            $extractedInfo['sender_name'] = $processedEmail->sender_name;
                        }
                        if (!isset($extractedInfo['account_number']) && $processedEmail->account_number) {
                            $extractedInfo['account_number'] = $processedEmail->account_number;
                        }
                    }

                    // Get unmatched stored emails with matching amount (same as PaymentController::checkMatch)
                    $emailDate = $processedEmail->email_date ? \Carbon\Carbon::parse($processedEmail->email_date) : null;
                    
                    // Find payments with matching amount (same as PaymentController::checkMatch)
                    $potentialPayments = Payment::where('status', Payment::STATUS_PENDING)
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

                    $matchedPayment = null;
                    foreach ($potentialPayments as $potentialPayment) {
                        // Use matchPayment() directly (same as PaymentController::checkMatch)
                        $match = $matchingService->matchPayment($potentialPayment, $extractedInfo, $emailDate);

                        // Log match attempt (same as PaymentController::checkMatch)
                        try {
                            $matchLogger->logAttempt([
                                'payment_id' => $potentialPayment->id,
                                'processed_email_id' => $processedEmail->id,
                                'transaction_id' => $potentialPayment->transaction_id,
                                'match_result' => $match['matched'] ? \App\Models\MatchAttempt::RESULT_MATCHED : \App\Models\MatchAttempt::RESULT_UNMATCHED,
                                'reason' => $match['reason'] ?? 'Unknown reason',
                                'payment_amount' => $potentialPayment->amount,
                                'payment_name' => $potentialPayment->payer_name,
                                'payment_account_number' => $potentialPayment->account_number,
                                'payment_created_at' => $potentialPayment->created_at,
                                'extracted_amount' => $extractedInfo['amount'] ?? null,
                                'extracted_name' => $extractedInfo['sender_name'] ?? null,
                                'extracted_account_number' => $extractedInfo['account_number'] ?? null,
                                'email_subject' => $processedEmail->subject,
                                'email_from' => $processedEmail->from_email,
                                'email_date' => $emailDate,
                                'amount_diff' => $match['amount_diff'] ?? null,
                                'name_similarity_percent' => $match['name_similarity_percent'] ?? null,
                                'time_diff_minutes' => $match['time_diff_minutes'] ?? null,
                                'extraction_method' => $extractionMethod,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to log match attempt in global match', [
                                'error' => $e->getMessage(),
                                'payment_id' => $potentialPayment->id,
                                'email_id' => $processedEmail->id,
                            ]);
                        }

                        if ($match['matched']) {
                            $matchedPayment = $potentialPayment;
                            break;
                        }
                    }

                    if ($matchedPayment) {
                        $results['matches_found']++;
                        $results['matched_emails'][] = [
                            'email_id' => $processedEmail->id,
                            'email_subject' => $processedEmail->subject,
                            'transaction_id' => $matchedPayment->transaction_id,
                            'payment_id' => $matchedPayment->id,
                        ];

                        // Mark email as matched (same as PaymentController::checkMatch)
                        $processedEmail->markAsMatched($matchedPayment);

                        // Approve payment (same as PaymentController::checkMatch)
                        $matchedPayment->approve([
                            'subject' => $processedEmail->subject,
                            'from' => $processedEmail->from_email,
                            'text' => $processedEmail->text_body,
                            'html' => $processedEmail->html_body,
                            'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                            'sender_name' => $processedEmail->sender_name,
                        ]);
                        
                        // Update payer_account_number if extracted (same as PaymentController::checkMatch)
                        if (isset($extractedInfo['payer_account_number']) && $extractedInfo['payer_account_number']) {
                            $matchedPayment->update(['payer_account_number' => $extractedInfo['payer_account_number']]);
                        }

                        // Update business balance (same as PaymentController::checkMatch)
                        if ($matchedPayment->business_id) {
                            $matchedPayment->business->incrementBalanceWithCharges($matchedPayment->amount, $matchedPayment);
                            $matchedPayment->business->refresh(); // Refresh to get updated balance
                            
                            // Send new deposit notification
                            $matchedPayment->business->notify(new \App\Notifications\NewDepositNotification($matchedPayment));
                            
                            // Check for auto-withdrawal
                            $matchedPayment->business->triggerAutoWithdrawal();
                        }

                        // Dispatch event to send webhook (same as PaymentController::checkMatch)
                        event(new \App\Events\PaymentApproved($matchedPayment));

                        Log::info('Global match: Email matched to payment', [
                            'email_id' => $processedEmail->id,
                            'transaction_id' => $matchedPayment->transaction_id,
                        ]);
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'type' => 'email_match',
                        'email_id' => $processedEmail->id ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Error matching email in global match', [
                        'email_id' => $processedEmail->id ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Also check pending payments that weren't matched in the first pass
            // Use same logic as PaymentController::checkMatch (which works!)
            foreach ($pendingPayments as $payment) {
                try {
                    // Refresh to get latest status
                    $payment->refresh();
                    
                    // Skip if already matched or expired
                    if ($payment->status !== Payment::STATUS_PENDING || $payment->isExpired()) {
                        continue;
                    }

                    // Check if payment was already matched (from first pass)
                    if ($payment->status === Payment::STATUS_APPROVED) {
                        continue;
                    }

                    $results['payments_checked']++;

                    // Get unmatched stored emails with matching amount (same as PaymentController::checkMatch)
                    $storedEmails = ProcessedEmail::where('is_matched', false)
                        ->whereBetween('amount', [
                            $payment->amount - 1,
                            $payment->amount + 1
                        ])
                        ->where('email_date', '>=', $payment->created_at) // Email must be AFTER transaction creation
                        ->get();

                    $matchedEmail = null;
                    foreach ($storedEmails as $storedEmail) {
                        // Skip if already matched (from first pass)
                        $storedEmail->refresh();
                        if ($storedEmail->is_matched) {
                            continue;
                        }

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

                        if (!$extractedInfo || !isset($extractedInfo['amount']) || !$extractedInfo['amount']) {
                            continue;
                        }

                        $emailDate = $storedEmail->email_date ? \Carbon\Carbon::parse($storedEmail->email_date) : null;
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
                            Log::error('Failed to log match attempt in global match', [
                                'error' => $e->getMessage(),
                                'payment_id' => $payment->id,
                                'email_id' => $storedEmail->id,
                            ]);
                        }

                        if ($match['matched']) {
                            $matchedEmail = $storedEmail;

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

                            // Dispatch event to send webhook (same as PaymentController::checkMatch)
                            event(new \App\Events\PaymentApproved($payment));

                            $results['matches_found']++;
                            $results['matched_payments'][] = [
                                'transaction_id' => $payment->transaction_id,
                                'payment_id' => $payment->id,
                                'email_id' => $storedEmail->id,
                                'email_subject' => $storedEmail->subject,
                            ];

                            Log::info('Global match: Payment matched to email', [
                                'transaction_id' => $payment->transaction_id,
                                'email_id' => $storedEmail->id,
                            ]);

                            break; // One email per payment
                        }
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'type' => 'payment_check',
                        'transaction_id' => $payment->transaction_id ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Error checking payment in global match', [
                        'transaction_id' => $payment->transaction_id ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Count logged attempts (approximate from emails checked + payments checked)
            $results['attempts_logged'] = \App\Models\MatchAttempt::where('created_at', '>=', now()->subMinutes(1))->count();

            $message = sprintf(
                'Global match check completed! Checked %d emails and %d payments. Found %d matches. Logged %d attempts.',
                $results['emails_checked'],
                $results['payments_checked'],
                $results['matches_found'],
                $results['attempts_logged']
            );

            return response()->json([
                'success' => true,
                'message' => $message,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in global match trigger', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error triggering global match: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
