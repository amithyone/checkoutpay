<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TestEmailController;

Route::get('/', function () {
    return response()->json([
        'message' => 'Email Payment Gateway API',
        'version' => '1.0.0',
        'endpoints' => [
            'POST /api/v1/payment-request' => 'Submit payment request',
            'GET /api/v1/payment/{transactionId}' => 'Get payment status',
            'GET /api/v1/payments' => 'Get all payments',
            'GET /api/health' => 'Health check',
        ],
    ]);
});

// Setup routes (must be before any middleware that requires database)
Route::get('/setup', [SetupController::class, 'index'])->name('setup');
Route::post('/setup/test-database', [SetupController::class, 'testDatabase']);
Route::post('/setup/save-database', [SetupController::class, 'saveDatabase']);
Route::post('/setup/complete', [SetupController::class, 'complete']);

// Standalone email connection test (no auth required)
Route::get('/test-email', [TestEmailController::class, 'test'])->name('test.email');
Route::post('/test-email', [TestEmailController::class, 'test']);

// Cron job endpoints (for external cron services)

// IMAP Email Fetching Cron (requires IMAP to be enabled)
Route::get('/cron/monitor-emails', function () {
    try {
        // Check if IMAP is disabled
        $disableImap = \App\Models\Setting::get('disable_imap_fetching', false);
        
        if ($disableImap) {
            return response()->json([
                'success' => false,
                'message' => 'IMAP fetching is disabled. Use /cron/read-emails-direct instead.',
                'timestamp' => now()->toDateTimeString(),
            ], 400);
        }

        $startTime = microtime(true);
        
        // Get last cron run time from settings
        $lastCronRun = \App\Models\Setting::get('last_cron_run', null);
        
        if ($lastCronRun) {
            $lastCronTime = \Carbon\Carbon::parse($lastCronRun);
            // Only fetch emails since last cron run (with 1 minute buffer)
            $sinceDate = $lastCronTime->subMinutes(1);
        } else {
            // First run: fetch from last stored email or oldest pending payment
            $lastStoredEmail = \App\Models\ProcessedEmail::orderBy('email_date', 'desc')->first();
            if ($lastStoredEmail && $lastStoredEmail->email_date) {
                $sinceDate = $lastStoredEmail->email_date->subMinutes(1);
            } else {
                $oldestPendingPayment = \App\Models\Payment::pending()
                    ->orderBy('created_at', 'asc')
                    ->first();
                $sinceDate = $oldestPendingPayment 
                    ? $oldestPendingPayment->created_at->subMinutes(5)
                    : now()->subHours(1); // Default to last hour if nothing else
            }
        }
        
        // Store current cron run time BEFORE processing (so we don't miss emails)
        \App\Models\Setting::set('last_cron_run', now()->toDateTimeString(), 'string', 'system', 'Last time cron job was executed');
        
        // Call the IMAP monitoring command
        \Illuminate\Support\Facades\Artisan::call('payment:monitor-emails', [
            '--since' => $sinceDate->toDateTimeString(),
        ]);
        
        $output = \Illuminate\Support\Facades\Artisan::output();
        $executionTime = round(microtime(true) - $startTime, 2);
        
        return response()->json([
            'success' => true,
            'message' => 'Email monitoring (IMAP) completed',
            'method' => 'imap',
            'timestamp' => now()->toDateTimeString(),
            'last_cron_run' => $lastCronRun,
            'fetched_since' => $sinceDate->toDateTimeString(),
            'execution_time_seconds' => $executionTime,
            'output' => $output,
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Cron job error (IMAP)', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString(),
        ], 500);
    }
})->name('cron.monitor-emails');

// Direct Filesystem Email Reading Cron (RECOMMENDED for shared hosting)
Route::get('/cron/read-emails-direct', function () {
    try {
        $startTime = microtime(true);
        
        // Run direct filesystem email reading command
        \Illuminate\Support\Facades\Artisan::call('payment:read-emails-direct', ['--all' => true]);
        
        $output = \Illuminate\Support\Facades\Artisan::output();
        $executionTime = round(microtime(true) - $startTime, 2);
        
        return response()->json([
            'success' => true,
            'message' => 'Direct filesystem email reading completed',
            'method' => 'direct_filesystem',
            'timestamp' => now()->toDateTimeString(),
            'execution_time_seconds' => $executionTime,
            'output' => $output,
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Cron job error (Direct Filesystem)', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString(),
        ], 500);
    }
})->name('cron.read-emails-direct');

// Global Match Cron (matches all unmatched pending payments with unmatched emails)
Route::get('/cron/global-match', function () {
    try {
        $startTime = microtime(true);
        
        // Extract the logic from MatchController to avoid authentication issues
        $matchingService = new \App\Services\PaymentMatchingService(
            new \App\Services\TransactionLogService()
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
        $pendingPayments = \App\Models\Payment::where('status', \App\Models\Payment::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereNotExists(function ($query) {
                $query->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('processed_emails')
                    ->whereColumn('processed_emails.matched_payment_id', 'payments.id')
                    ->where('processed_emails.is_matched', true);
            })
            ->with('business')
            ->get();

        // Get all unmatched processed emails
        $unmatchedEmails = \App\Models\ProcessedEmail::where('is_matched', false)
            ->latest()
            ->get();

        \Illuminate\Support\Facades\Log::info('Global match cron triggered', [
            'pending_payments_count' => $pendingPayments->count(),
            'unmatched_emails_count' => $unmatchedEmails->count(),
        ]);

        // STEP 1: Parse description fields for emails that have them but missing account_number
        // This ensures account numbers are extracted before matching
        $parsedCount = 0;
        foreach ($unmatchedEmails as $processedEmail) {
            if ($processedEmail->description_field && !$processedEmail->account_number) {
                try {
                    $parsedData = parseDescriptionFieldHelper($processedEmail->description_field);
                    if ($parsedData['account_number']) {
                        $currentExtractedData = $processedEmail->extracted_data ?? [];
                        $currentExtractedData['description_field'] = $processedEmail->description_field;
                        $currentExtractedData['account_number'] = $parsedData['account_number'];
                        $currentExtractedData['payer_account_number'] = $parsedData['payer_account_number'];
                        $currentExtractedData['amount_from_description'] = $parsedData['amount'];
                        $currentExtractedData['date_from_description'] = $parsedData['extracted_date'];
                        
                        $processedEmail->update([
                            'account_number' => $parsedData['account_number'],
                            'extracted_data' => $currentExtractedData,
                        ]);
                        $parsedCount++;
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to parse description field in global match cron', [
                        'email_id' => $processedEmail->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        if ($parsedCount > 0) {
            \Illuminate\Support\Facades\Log::info("Parsed {$parsedCount} description fields before matching");
        }

        // Strategy: For each unmatched email, try to match against all pending payments
        foreach ($unmatchedEmails as $processedEmail) {
            try {
                $processedEmail->refresh();
                if ($processedEmail->is_matched) {
                    continue;
                }

                $results['emails_checked']++;

                $emailData = [
                    'subject' => $processedEmail->subject,
                    'from' => $processedEmail->from_email,
                    'text' => $processedEmail->text_body ?? '',
                    'html' => $processedEmail->html_body ?? '',
                    'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : null,
                    'email_account_id' => $processedEmail->email_account_id,
                    'processed_email_id' => $processedEmail->id,
                ];

                $matchedPayment = $matchingService->matchEmail($emailData);

                if ($matchedPayment) {
                    $results['matches_found']++;
                    $results['matched_emails'][] = [
                        'email_id' => $processedEmail->id,
                        'email_subject' => $processedEmail->subject,
                        'transaction_id' => $matchedPayment->transaction_id,
                        'payment_id' => $matchedPayment->id,
                    ];

                    \App\Jobs\ProcessEmailPayment::dispatchSync($emailData);
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'type' => 'email_match',
                    'email_id' => $processedEmail->id ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
                \Illuminate\Support\Facades\Log::error('Error matching email in global match cron', [
                    'email_id' => $processedEmail->id ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Also check pending payments that weren't matched in the first pass
        foreach ($pendingPayments as $payment) {
            try {
                $payment->refresh();
                
                if ($payment->status !== \App\Models\Payment::STATUS_PENDING || $payment->isExpired()) {
                    continue;
                }

                if ($payment->status === \App\Models\Payment::STATUS_APPROVED) {
                    continue;
                }

                $results['payments_checked']++;

                $checkSince = $payment->created_at->subMinutes(5);
                $timeWindowMinutes = \App\Models\Setting::get('payment_time_window_minutes', 120);
                $checkUntil = $payment->created_at->addMinutes($timeWindowMinutes);
                
                $potentialEmails = \App\Models\ProcessedEmail::where('is_matched', false)
                    ->where(function ($q) use ($payment, $checkSince, $checkUntil) {
                        $q->where('amount', $payment->amount)
                            ->where('email_date', '>=', $checkSince)
                            ->where('email_date', '<=', $checkUntil);
                    })
                    ->get();

                foreach ($potentialEmails as $processedEmail) {
                    try {
                        $processedEmail->refresh();
                        if ($processedEmail->is_matched) {
                            continue;
                        }

                        $emailData = [
                            'subject' => $processedEmail->subject,
                            'from' => $processedEmail->from_email,
                            'text' => $processedEmail->text_body ?? '',
                            'html' => $processedEmail->html_body ?? '',
                            'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : null,
                            'email_account_id' => $processedEmail->email_account_id,
                            'processed_email_id' => $processedEmail->id,
                        ];

                        $matchedPayment = $matchingService->matchEmail($emailData);

                        if ($matchedPayment && $matchedPayment->id === $payment->id) {
                            $results['matches_found']++;
                            $results['matched_payments'][] = [
                                'transaction_id' => $payment->transaction_id,
                                'payment_id' => $payment->id,
                                'email_id' => $processedEmail->id,
                                'email_subject' => $processedEmail->subject,
                            ];

                            \App\Jobs\ProcessEmailPayment::dispatchSync($emailData);
                            break;
                        }
                    } catch (\Exception $e) {
                        $results['errors'][] = [
                            'type' => 'payment_match',
                            'transaction_id' => $payment->transaction_id,
                            'email_id' => $processedEmail->id ?? 'unknown',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'type' => 'payment_check',
                    'transaction_id' => $payment->transaction_id ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $results['attempts_logged'] = \App\Models\MatchAttempt::where('created_at', '>=', now()->subMinutes(1))->count();
        
        $executionTime = round(microtime(true) - $startTime, 2);
        $results['execution_time_seconds'] = $executionTime;

        $message = sprintf(
            'Global match completed! Checked %d emails and %d payments. Found %d matches. Logged %d attempts. Execution time: %s seconds.',
            $results['emails_checked'],
            $results['payments_checked'],
            $results['matches_found'],
            $results['attempts_logged'],
            $executionTime
        );

        return response()->json([
            'success' => true,
            'message' => $message,
            'method' => 'global_match',
            'timestamp' => now()->toDateTimeString(),
            'results' => $results,
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Cron job error (Global Match)', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString(),
        ], 500);
    }
    
    /**
     * Helper function to parse description field
     */
    if (!function_exists('parseDescriptionFieldHelper')) {
        function parseDescriptionFieldHelper(?string $descriptionField): array
        {
            $result = [
                'account_number' => null,
                'payer_account_number' => null,
                'amount' => null,
                'extracted_date' => null,
            ];

            if (!$descriptionField) {
                return $result;
            }

            $length = strlen($descriptionField);

            // Handle 43-digit format
            if ($length >= 43) {
                if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})/', $descriptionField, $matches)) {
                    $result['account_number'] = trim($matches[1]);
                    $result['payer_account_number'] = trim($matches[2]);
                    $result['amount'] = (float) ($matches[3] / 100);
                    $dateStr = $matches[4];
                    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                        $result['extracted_date'] = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                    }
                }
            }
            // Handle 42-digit format
            elseif ($length == 42) {
                $padded = $descriptionField . '0';
                if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})/', $padded, $matches)) {
                    $result['account_number'] = trim($matches[1]);
                    $result['payer_account_number'] = trim($matches[2]);
                    $result['amount'] = (float) ($matches[3] / 100);
                    $dateStr = $matches[4];
                    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                        $result['extracted_date'] = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                    }
                }
            }
            // Handle 30-digit format
            elseif ($length >= 30) {
                if (preg_match('/^(\d{10})(\d{10})/', $descriptionField, $matches)) {
                    $result['account_number'] = trim($matches[1]);
                    $result['payer_account_number'] = trim($matches[2]);
                    if (preg_match('/^(\d{10})(\d{10})(\d{6})/', $descriptionField, $amountMatches)) {
                        $result['amount'] = (float) ($amountMatches[3] / 100);
                    }
                }
            }

            return $result;
        }
    }
})->name('cron.global-match');
