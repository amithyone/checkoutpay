<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ProcessedEmail;
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

            // STEP 1: Parse description fields for emails that have them but missing account_number
            // This ensures account numbers are extracted before matching
            $parsedCount = 0;
            foreach ($unmatchedEmails as $processedEmail) {
                if ($processedEmail->description_field && !$processedEmail->account_number) {
                    try {
                        $parsedData = $this->parseDescriptionField($processedEmail->description_field);
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

            // Strategy: For each unmatched email, try to match against all pending payments
            // This uses the PaymentMatchingService.matchEmail which automatically logs all attempts
            foreach ($unmatchedEmails as $processedEmail) {
                try {
                    // Skip if already matched (in case it was matched in this run)
                    $processedEmail->refresh();
                    if ($processedEmail->is_matched) {
                        continue;
                    }

                    $results['emails_checked']++;

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

                    // Try to match email against all pending payments
                    // This will use matchEmail which logs attempts automatically for each payment tried
                    $matchedPayment = $matchingService->matchEmail($emailData);

                    if ($matchedPayment) {
                        $results['matches_found']++;
                        $results['matched_emails'][] = [
                            'email_id' => $processedEmail->id,
                            'email_subject' => $processedEmail->subject,
                            'transaction_id' => $matchedPayment->transaction_id,
                            'payment_id' => $matchedPayment->id,
                        ];

                        // Process the email payment (approve payment)
                        // This will also update the email as matched and approve the payment
                        \App\Jobs\ProcessEmailPayment::dispatchSync($emailData);

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
            // This ensures we catch any payments that were created but no email was found yet
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

                    // Get unmatched emails that could potentially match this payment
                    $checkSince = $payment->created_at->subMinutes(5);
                    $timeWindowMinutes = \App\Models\Setting::get('payment_time_window_minutes', 120);
                    $checkUntil = $payment->created_at->addMinutes($timeWindowMinutes);
                    
                    $potentialEmails = ProcessedEmail::where('is_matched', false)
                        ->where(function ($q) use ($payment, $checkSince, $checkUntil) {
                            $q->where('amount', $payment->amount)
                                ->where('email_date', '>=', $checkSince)
                                ->where('email_date', '<=', $checkUntil);
                        })
                        ->get();

                    foreach ($potentialEmails as $processedEmail) {
                        try {
                            // Skip if already matched (from first pass)
                            $processedEmail->refresh();
                            if ($processedEmail->is_matched) {
                                continue;
                            }

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

                            // Try to match email to this specific payment
                            // This will log attempts automatically
                            $matchedPayment = $matchingService->matchEmail($emailData);

                            if ($matchedPayment && $matchedPayment->id === $payment->id) {
                                $results['matches_found']++;
                                $results['matched_payments'][] = [
                                    'transaction_id' => $payment->transaction_id,
                                    'payment_id' => $payment->id,
                                    'email_id' => $processedEmail->id,
                                    'email_subject' => $processedEmail->subject,
                                ];

                                // Process the email payment (approve payment)
                                \App\Jobs\ProcessEmailPayment::dispatchSync($emailData);

                                Log::info('Global match: Payment matched to email', [
                                    'transaction_id' => $payment->transaction_id,
                                    'email_id' => $processedEmail->id,
                                ]);

                                // Break after first match (one email per payment)
                                break;
                            }
                        } catch (\Exception $e) {
                            $results['errors'][] = [
                                'type' => 'payment_match',
                                'transaction_id' => $payment->transaction_id,
                                'email_id' => $processedEmail->id ?? 'unknown',
                                'error' => $e->getMessage(),
                            ];
                            Log::error('Error matching payment to email in global match', [
                                'transaction_id' => $payment->transaction_id,
                                'email_id' => $processedEmail->id ?? 'unknown',
                                'error' => $e->getMessage(),
                            ]);
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
