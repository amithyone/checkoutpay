<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TestEmailController;

Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/pricing', [\App\Http\Controllers\PricingController::class, 'index'])->name('pricing');

// Dynamic pages (Privacy Policy, Terms, etc.)
Route::get('/page/{slug}', [\App\Http\Controllers\PageController::class, 'show'])->name('page.show');

// Static page routes for common pages
Route::get('/privacy-policy', function () {
    $page = \App\Models\Page::getBySlug('privacy-policy');
    if (!$page) {
        abort(404);
    }
    return view('page', compact('page'));
})->name('privacy-policy');

Route::get('/terms-and-conditions', function () {
    $page = \App\Models\Page::getBySlug('terms-and-conditions');
    if (!$page) {
        abort(404);
    }
    return view('page', compact('page'));
})->name('terms');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

// Setup routes (must be before any middleware that requires database)
Route::get('/setup', [SetupController::class, 'index'])->name('setup');
Route::post('/setup/test-database', [SetupController::class, 'testDatabase']);
Route::post('/setup/save-database', [SetupController::class, 'saveDatabase']);
Route::post('/setup/complete', [SetupController::class, 'complete']);

// Standalone email connection test (no auth required)
Route::get('/test-email', [TestEmailController::class, 'test'])->name('test.email');
Route::post('/test-email', [TestEmailController::class, 'test']);

// Hosted checkout page routes (public)
Route::get('/pay', [\App\Http\Controllers\CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/pay', [\App\Http\Controllers\CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/pay/{transactionId}', [\App\Http\Controllers\CheckoutController::class, 'payment'])->name('checkout.payment');
Route::get('/pay/{transactionId}/status', [\App\Http\Controllers\CheckoutController::class, 'checkStatus'])->name('checkout.status');

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

// Direct Filesystem Email Reading Cron (PUBLIC ENDPOINT - for cron job websites)
// FIXED: Use same logic as admin dashboard button - calls the same controller method
// Reads from filesystem regardless of email account method setting
// Optional security: Add ?token=YOUR_SECRET_TOKEN to protect the endpoint
Route::get('/cron/read-emails-direct', function (\Illuminate\Http\Request $request) {
    try {
        // Optional security: Check for secret token if configured
        $requiredToken = env('CRON_EMAIL_FETCH_TOKEN');
        if ($requiredToken) {
            $providedToken = $request->query('token') ?? $request->header('X-Cron-Token');
            if ($providedToken !== $requiredToken) {
                \Illuminate\Support\Facades\Log::warning('Unauthorized cron access attempt', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Invalid or missing token',
                    'timestamp' => now()->toDateTimeString(),
                ], 401);
            }
        }
        
        $startTime = microtime(true);
        
        // Use the same controller method as the admin dashboard button
        // This ensures emails are processed and matched exactly like the dashboard button
        $controller = new \App\Http\Controllers\Admin\EmailMonitorController();
        
        $response = $controller->fetchEmailsDirect($request);
        $responseData = json_decode($response->getContent(), true);
        
        $executionTime = round(microtime(true) - $startTime, 2);
        
        // Add execution time to response
        if ($responseData) {
            $responseData['execution_time_seconds'] = $executionTime;
            $responseData['method'] = 'direct_filesystem';
            $responseData['timestamp'] = now()->toDateTimeString();
        }
        
        return response()->json($responseData, $response->getStatusCode());
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

// Fill Sender Names from text_body Cron (STEP 2)
Route::get('/cron/fill-sender-names', function () {
    try {
        $startTime = microtime(true);
        
        // STEP 2: Fill in sender_name from text_body if it's null
        \Illuminate\Support\Facades\Artisan::call('payment:re-extract-text-body', [
            '--missing-only' => true,
        ]);
        
        $output = \Illuminate\Support\Facades\Artisan::output();
        $executionTime = round(microtime(true) - $startTime, 2);
        
        return response()->json([
            'success' => true,
            'message' => 'Sender name extraction from text_body completed',
            'method' => 'fill_sender_names',
            'timestamp' => now()->toDateTimeString(),
            'execution_time_seconds' => $executionTime,
            'output' => $output,
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Cron job error (Fill Sender Names)', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString(),
        ], 500);
    }
})->name('cron.fill-sender-names');

// Extract Missing Names Cron (Advanced Name Extraction)
// Extract Missing Names Cron (PUBLIC ENDPOINT - for cron job websites)
// Extracts sender names from emails that don't have names yet
// Uses same logic as admin dashboard button - calls the same controller method
// Optional security: Add ?token=YOUR_SECRET_TOKEN to protect the endpoint
Route::get('/cron/extract-missing-names', function (\Illuminate\Http\Request $request) {
    try {
        // Optional security: Check for secret token if configured
        $requiredToken = env('CRON_EMAIL_FETCH_TOKEN'); // Reuse same token as email fetch
        if ($requiredToken) {
            $providedToken = $request->query('token') ?? $request->header('X-Cron-Token');
            if ($providedToken !== $requiredToken) {
                \Illuminate\Support\Facades\Log::warning('Unauthorized cron access attempt (Extract Names)', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Invalid or missing token',
                    'timestamp' => now()->toDateTimeString(),
                ], 401);
            }
        }
        
        $startTime = microtime(true);
        
        // Use the same controller method as the admin dashboard button
        // This ensures name extraction works exactly like the dashboard button
        $controller = new \App\Http\Controllers\Admin\DashboardController();
        
        // Get limit from query parameter (default 50, same as dashboard button)
        $limit = (int) $request->query('limit', 50);
        $request->merge(['limit' => $limit]);
        
        $response = $controller->extractMissingNames($request);
        $responseData = json_decode($response->getContent(), true);
        
        $executionTime = round(microtime(true) - $startTime, 2);
        
        // Add execution time to response
        if ($responseData) {
            $responseData['execution_time_seconds'] = $executionTime;
            $responseData['method'] = 'extract_missing_names';
            $responseData['timestamp'] = now()->toDateTimeString();
        }
        
        return response()->json($responseData, $response->getStatusCode());
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Cron job error (Extract Missing Names)', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString(),
        ], 500);
    }
})->name('cron.extract-missing-names');

// Master Email Processing Cron (All 3 Steps Sequentially)
Route::get('/cron/process-emails', function () {
    try {
        $overallStartTime = microtime(true);
        $results = [
            'step1_fetch' => ['success' => false, 'message' => '', 'execution_time' => 0],
            'step2_fill_sender' => ['success' => false, 'message' => '', 'execution_time' => 0],
            'step3_match' => ['success' => false, 'message' => '', 'execution_time' => 0],
        ];
        
        // ============================================
        // STEP 1: Fetch emails from filesystem
        // ============================================
        \Illuminate\Support\Facades\Log::info('STEP 1: Starting email fetch from filesystem');
        $step1Start = microtime(true);
        try {
            \Illuminate\Support\Facades\Artisan::call('payment:read-emails-direct', [
                '--all' => true,
                '--no-match' => true, // Skip matching in this step
            ]);
            $step1Output = \Illuminate\Support\Facades\Artisan::output();
            $results['step1_fetch'] = [
                'success' => true,
                'message' => 'Emails fetched from filesystem',
                'execution_time' => round(microtime(true) - $step1Start, 2),
                'output' => $step1Output,
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('STEP 1 failed: Email fetch', [
                'error' => $e->getMessage(),
            ]);
            $results['step1_fetch'] = [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'execution_time' => round(microtime(true) - $step1Start, 2),
            ];
        }
        
        // ============================================
        // STEP 2: Fill sender_name from text_body if null
        // ============================================
        \Illuminate\Support\Facades\Log::info('STEP 2: Starting sender_name extraction from text_body');
        $step2Start = microtime(true);
        try {
            \Illuminate\Support\Facades\Artisan::call('payment:re-extract-text-body', [
                '--missing-only' => true,
            ]);
            $step2Output = \Illuminate\Support\Facades\Artisan::output();
            $results['step2_fill_sender'] = [
                'success' => true,
                'message' => 'Sender names extracted from text_body',
                'execution_time' => round(microtime(true) - $step2Start, 2),
                'output' => $step2Output,
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('STEP 2 failed: Fill sender names', [
                'error' => $e->getMessage(),
            ]);
            $results['step2_fill_sender'] = [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'execution_time' => round(microtime(true) - $step2Start, 2),
            ];
        }
        
        // ============================================
        // STEP 3: Match transactions
        // ============================================
        \Illuminate\Support\Facades\Log::info('STEP 3: Starting transaction matching');
        $step3Start = microtime(true);
        try {
            $matchingService = new \App\Services\PaymentMatchingService(
                new \App\Services\TransactionLogService()
            );
            $descriptionExtractor = new \App\Services\DescriptionFieldExtractor();

            $matchResults = [
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

            // STEP 3a: Extract missing sender_name and description_field from text_body (if still needed)
            $textBodyExtractedCount = 0;
            foreach ($unmatchedEmails as $processedEmail) {
                if (!$processedEmail->sender_name || !$processedEmail->description_field) {
                    try {
                        $extracted = $matchingService->extractMissingFromTextBody($processedEmail);
                        if ($extracted) {
                            $textBodyExtractedCount++;
                            $processedEmail->refresh();
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to extract from text_body in step 3', [
                            'email_id' => $processedEmail->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // STEP 3b: Parse description fields for emails that have them but missing account_number
            $parsedCount = 0;
            foreach ($unmatchedEmails as $processedEmail) {
                $processedEmail->refresh();
                if ($processedEmail->description_field && !$processedEmail->account_number) {
                    try {
                        $parsedData = $descriptionExtractor->parseDescriptionField($processedEmail->description_field);
                        if ($parsedData['account_number']) {
                            $currentExtractedData = $processedEmail->extracted_data ?? [];
                            $currentExtractedData['description_field'] = $processedEmail->description_field;
                            $currentExtractedData['account_number'] = $parsedData['account_number'];
                            $currentExtractedData['payer_account_number'] = $parsedData['payer_account_number'];
                            $currentExtractedData['date_from_description'] = $parsedData['extracted_date'];
                            
                            $processedEmail->update([
                                'account_number' => $parsedData['account_number'],
                                'extracted_data' => $currentExtractedData,
                            ]);
                            $parsedCount++;
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to parse description field in step 3', [
                            'email_id' => $processedEmail->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // STEP 3c: Match emails to payments
            foreach ($unmatchedEmails as $processedEmail) {
                try {
                    $processedEmail->refresh();
                    if ($processedEmail->is_matched) {
                        continue;
                    }

                    $matchResults['emails_checked']++;

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
                        $matchResults['matches_found']++;
                        $matchResults['matched_emails'][] = [
                            'email_id' => $processedEmail->id,
                            'email_subject' => $processedEmail->subject,
                            'transaction_id' => $matchedPayment->transaction_id,
                            'payment_id' => $matchedPayment->id,
                        ];

                        \App\Jobs\ProcessEmailPayment::dispatchSync($emailData);
                    }
                } catch (\Exception $e) {
                    $matchResults['errors'][] = [
                        'type' => 'email_match',
                        'email_id' => $processedEmail->id ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                    \Illuminate\Support\Facades\Log::error('Error matching email in step 3', [
                        'email_id' => $processedEmail->id ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Also check pending payments
            foreach ($pendingPayments as $payment) {
                try {
                    $payment->refresh();
                    
                    if ($payment->status !== \App\Models\Payment::STATUS_PENDING || $payment->isExpired()) {
                        continue;
                    }

                    $matchResults['payments_checked']++;
                    $matchedEmail = $matchingService->matchPaymentToStoredEmail($payment);
                    
                    if ($matchedEmail) {
                        $matchResults['matches_found']++;
                        $matchResults['matched_payments'][] = [
                            'transaction_id' => $payment->transaction_id,
                            'payment_id' => $payment->id,
                            'email_id' => $matchedEmail->id,
                        ];
                    }
                } catch (\Exception $e) {
                    $matchResults['errors'][] = [
                        'type' => 'payment_match',
                        'transaction_id' => $payment->transaction_id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $matchResults['attempts_logged'] = \App\Models\MatchAttempt::where('created_at', '>=', now()->subMinutes(1))->count();
            
            $results['step3_match'] = [
                'success' => true,
                'message' => sprintf(
                    'Matching completed: %d emails checked, %d payments checked, %d matches found, %d attempts logged',
                    $matchResults['emails_checked'],
                    $matchResults['payments_checked'],
                    $matchResults['matches_found'],
                    $matchResults['attempts_logged']
                ),
                'execution_time' => round(microtime(true) - $step3Start, 2),
                'results' => $matchResults,
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('STEP 3 failed: Transaction matching', [
                'error' => $e->getMessage(),
            ]);
            $results['step3_match'] = [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'execution_time' => round(microtime(true) - $step3Start, 2),
            ];
        }
        
        $totalExecutionTime = round(microtime(true) - $overallStartTime, 2);
        
        \Illuminate\Support\Facades\Log::info('Master email processing cron completed', [
            'total_time' => $totalExecutionTime,
            'step1' => $results['step1_fetch']['success'],
            'step2' => $results['step2_fill_sender']['success'],
            'step3' => $results['step3_match']['success'],
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Email processing completed (3 steps)',
            'method' => 'process_emails_master',
            'timestamp' => now()->toDateTimeString(),
            'total_execution_time_seconds' => $totalExecutionTime,
            'steps' => $results,
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Master email processing cron error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'timestamp' => now()->toDateTimeString(),
        ], 500);
    }
})->name('cron.process-emails');

// Global Match Cron (matches all unmatched pending payments with unmatched emails)
Route::get('/cron/global-match', function () {
    try {
        $startTime = microtime(true);
        
        // Extract the logic from MatchController to avoid authentication issues
        $matchingService = new \App\Services\PaymentMatchingService(
            new \App\Services\TransactionLogService()
        );
        $descriptionExtractor = new \App\Services\DescriptionFieldExtractor();

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

        // Get all unmatched processed emails that have amounts (required for matching)
        $unmatchedEmails = \App\Models\ProcessedEmail::where('is_matched', false)
            ->whereNotNull('amount')
            ->where('amount', '>', 0)
            ->latest()
            ->get();

        \Illuminate\Support\Facades\Log::info('Global match cron triggered', [
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
                    \Illuminate\Support\Facades\Log::error('Failed to extract from text_body in global match cron', [
                        'email_id' => $processedEmail->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        if ($textBodyExtractedCount > 0) {
            \Illuminate\Support\Facades\Log::info("Extracted {$textBodyExtractedCount} missing fields from text_body before matching");
        }
        
        // STEP 2: Parse description fields for emails that have them but missing account_number
        // This ensures account numbers are extracted before matching
        $descriptionExtractor = new \App\Services\DescriptionFieldExtractor();
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
        // FIXED: Use same approach as admin checkMatch - use matchPayment() directly instead of matchEmail()
        foreach ($unmatchedEmails as $processedEmail) {
            try {
                $processedEmail->refresh();
                if ($processedEmail->is_matched) {
                    continue;
                }

                // Skip emails without amount (can't match without amount)
                if (!$processedEmail->amount || $processedEmail->amount <= 0) {
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

                // Extract payment info from email (same as admin checkMatch)
                $extractionResult = $matchingService->extractPaymentInfo($emailData);
                
                // Handle new format: ['data' => [...], 'method' => '...']
                $extractedInfo = null;
                if (is_array($extractionResult) && isset($extractionResult['data'])) {
                    $extractedInfo = $extractionResult['data'];
                } else {
                    $extractedInfo = $extractionResult; // Old format fallback
                }

                // Use stored values as fallback if extraction fails (same as admin checkMatch)
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

                // Find payments with matching amount and time constraints (same as admin checkMatch)
                $emailDate = $processedEmail->email_date ? \Carbon\Carbon::parse($processedEmail->email_date) : null;
                
                // CRITICAL: Only check payments created BEFORE email was received
                $potentialPayments = \App\Models\Payment::where('status', \App\Models\Payment::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->whereBetween('amount', [
                        $extractedInfo['amount'] - 1,
                        $extractedInfo['amount'] + 1
                    ])
                    ->where('created_at', '<=', $emailDate ?? now()) // Payment must be created BEFORE email
                    ->orderBy('created_at', 'desc') // Check newest payments first
                    ->get();
                
                $matchedPayment = null;
                foreach ($potentialPayments as $potentialPayment) {
                    // Use matchPayment() directly (same as admin checkMatch)
                    $matchResult = $matchingService->matchPayment($potentialPayment, $extractedInfo, $emailDate);
                    
                    if ($matchResult['matched']) {
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

                    // Process the email payment (approve payment)
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
                    'trace' => $e->getTraceAsString(),
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

                // CRITICAL: Only check emails received AFTER transaction creation
                $timeWindowMinutes = \App\Models\Setting::get('payment_time_window_minutes', 120);
                $checkUntil = $payment->created_at->copy()->addMinutes($timeWindowMinutes);
                
                // Filter emails by amount (within 1 naira tolerance) and time window
                $potentialEmails = \App\Models\ProcessedEmail::where('is_matched', false)
                    ->whereNotNull('amount')
                    ->where('amount', '>', 0)
                    ->whereBetween('amount', [
                        $payment->amount - 1,
                        $payment->amount + 1
                    ])
                    ->where('email_date', '>=', $payment->created_at) // Email must be AFTER transaction creation
                    ->where('email_date', '<=', $checkUntil)
                    ->get();

                foreach ($potentialEmails as $processedEmail) {
                    try {
                        $processedEmail->refresh();
                        if ($processedEmail->is_matched) {
                            continue;
                        }

                        // Extract payment info from email
                        $emailData = [
                            'subject' => $processedEmail->subject,
                            'from' => $processedEmail->from_email,
                            'text' => $processedEmail->text_body ?? '',
                            'html' => $processedEmail->html_body ?? '',
                            'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : null,
                            'email_account_id' => $processedEmail->email_account_id,
                            'processed_email_id' => $processedEmail->id,
                        ];

                        // Extract payment info from email
                        $extractionResult = $matchingService->extractPaymentInfo($emailData);
                        
                        // Use stored values as fallback if extraction fails
                        if (!$extractionResult || !isset($extractionResult['data'])) {
                            // Fallback to stored values from database
                            $extractedInfo = [
                                'amount' => $processedEmail->amount,
                                'sender_name' => $processedEmail->sender_name,
                                'account_number' => $processedEmail->account_number,
                            ];
                        } else {
                            $extractedInfo = $extractionResult['data'];
                            // Merge stored values if extraction didn't provide them
                            if (!isset($extractedInfo['amount']) && $processedEmail->amount) {
                                $extractedInfo['amount'] = $processedEmail->amount;
                            }
                            if (!isset($extractedInfo['sender_name']) && $processedEmail->sender_name) {
                                $extractedInfo['sender_name'] = $processedEmail->sender_name;
                            }
                        }
                        
                        // Use matchPayment directly to check this specific payment
                        $emailDate = $processedEmail->email_date ? \Carbon\Carbon::parse($processedEmail->email_date) : null;
                        $matchResult = $matchingService->matchPayment($payment, $extractedInfo, $emailDate);

                        if ($matchResult['matched']) {
                            $results['matches_found']++;
                            $results['matched_payments'][] = [
                                'transaction_id' => $payment->transaction_id,
                                'payment_id' => $payment->id,
                                'email_id' => $processedEmail->id,
                                'email_subject' => $processedEmail->subject,
                                'match_reason' => $matchResult['reason'] ?? 'Matched',
                            ];

                            // Process the email payment (approve payment)
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
                        \Illuminate\Support\Facades\Log::error('Error matching payment to email in global match cron', [
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
    
    // Helper function removed - now using DescriptionFieldExtractor service instead
})->name('cron.global-match');
