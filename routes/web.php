<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\TestEmailController;

Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/products', [\App\Http\Controllers\ProductsController::class, 'index'])->name('products.index');
Route::get('/products/invoices', [\App\Http\Controllers\ProductsController::class, 'invoices'])->name('products.invoices');
Route::get('/products/memberships', [\App\Http\Controllers\ProductsController::class, 'memberships'])->name('products.memberships');
Route::get('/products/memberships-info', [\App\Http\Controllers\ProductsController::class, 'membershipsInfo'])->name('products.memberships-info');
Route::get('/products/rentals-info', [\App\Http\Controllers\ProductsController::class, 'rentalsInfo'])->name('products.rentals-info');
Route::get('/products/tickets-info', [\App\Http\Controllers\ProductsController::class, 'ticketsInfo'])->name('products.tickets-info');
Route::get('/marketplace', [\App\Http\Controllers\Public\MarketplaceController::class, 'index'])->name('marketplace.index');
Route::get('/tickets', [\App\Http\Controllers\Public\TicketsController::class, 'index'])->name('tickets.index');
Route::get('/payout', [\App\Http\Controllers\PayoutController::class, 'index'])->name('payout.index');
Route::get('/collections', [\App\Http\Controllers\CollectionsController::class, 'index'])->name('collections.index');
Route::get('/checkout-demo', [\App\Http\Controllers\CheckoutDemoController::class, 'index'])->name('checkout-demo.index');
Route::get('/about-us', [\App\Http\Controllers\AboutController::class, 'index'])->name('about.index');
Route::get('/blog', [\App\Http\Controllers\BlogController::class, 'index'])->name('blog.index');
Route::get('/faqs', [\App\Http\Controllers\FaqsController::class, 'index'])->name('faqs.index');
Route::get('/status', [\App\Http\Controllers\StatusController::class, 'index'])->name('status.index');
Route::get('/security', function () {
    $page = \App\Models\Page::getBySlug('security');
    if (!$page) {
        abort(404);
    }
    return view('page', compact('page'));
})->name('security');
Route::get('/esg-policy', function () {
    $page = \App\Models\Page::getBySlug('esg-policy');
    if (!$page) {
        abort(404);
    }
    return view('page', compact('page'));
})->name('esg-policy');
Route::get('/fraud-awareness', function () {
    $page = \App\Models\Page::getBySlug('fraud-awareness');
    if (!$page) {
        abort(404);
    }
    return view('page', compact('page'));
})->name('fraud-awareness');
Route::get('/resources', [\App\Http\Controllers\ResourcesController::class, 'index'])->name('resources.index');
Route::get('/developers', [\App\Http\Controllers\DevelopersController::class, 'index'])->name('developers.index');
Route::get('/support', [\App\Http\Controllers\SupportController::class, 'index'])->name('support.index');
Route::get('/pricing', [\App\Http\Controllers\PricingController::class, 'index'])->name('pricing');
Route::get('/api-docs', [\App\Http\Controllers\ApiDocsController::class, 'index'])->name('api-docs');

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

// Public ticket routes
Route::prefix('tickets')->name('tickets.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Public\TicketsController::class, 'index'])->name('index');
    Route::get('event/{event}', [\App\Http\Controllers\Public\TicketController::class, 'show'])->name('show');
    Route::post('event/{event}/purchase', [\App\Http\Controllers\Public\TicketController::class, 'purchase'])->name('purchase');
    Route::get('order/{orderNumber}', [\App\Http\Controllers\Public\TicketController::class, 'order'])->name('order');
    Route::get('order/{orderNumber}/download', [\App\Http\Controllers\Public\TicketController::class, 'download'])->name('download');
    Route::post('payment/webhook/{orderNumber}', [\App\Http\Controllers\Public\TicketController::class, 'paymentWebhook'])->name('payment.webhook');
    // Legacy route for backward compatibility
    Route::get('{event}', [\App\Http\Controllers\Public\TicketController::class, 'show'])->name('show-legacy');
});

// Public invoice payment routes
Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('pay/{code}', [\App\Http\Controllers\Public\InvoicePaymentController::class, 'show'])->name('pay');
    Route::post('pay/{code}/webhook', [\App\Http\Controllers\Public\InvoicePaymentController::class, 'webhook'])->name('payment.webhook');
});

// Public memberships routes
Route::prefix('memberships')->name('memberships.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Public\MembershipController::class, 'index'])->name('index');
    Route::get('{slug}', [\App\Http\Controllers\Public\MembershipController::class, 'show'])->name('show');
    
    // Payment flow
    Route::get('{slug}/payment', [\App\Http\Controllers\Public\MembershipPaymentController::class, 'show'])->name('payment.show');
    Route::post('{slug}/payment', [\App\Http\Controllers\Public\MembershipPaymentController::class, 'process'])->name('payment.process');
    Route::post('{slug}/payment/webhook', [\App\Http\Controllers\Public\MembershipPaymentController::class, 'webhook'])->name('payment.webhook');
    Route::get('success/{subscriptionNumber}', [\App\Http\Controllers\Public\MembershipPaymentController::class, 'success'])->name('payment.success');
    
    // Card download
    Route::get('card/{subscriptionNumber}/download', [\App\Http\Controllers\Public\MembershipCardController::class, 'download'])->name('card.download');
    Route::get('card/{subscriptionNumber}/view', [\App\Http\Controllers\Public\MembershipCardController::class, 'view'])->name('card.view');
});

// Public rentals routes
Route::prefix('rentals')->name('rentals.')->group(function () {
    // Public browsing (no auth required)
    Route::get('/', [\App\Http\Controllers\Public\RentalController::class, 'index'])->name('index');
    Route::get('item/{slug}', [\App\Http\Controllers\Public\RentalController::class, 'show'])->name('show');
    
    // Cart operations (no auth required)
    Route::post('cart/add', [\App\Http\Controllers\Public\RentalRequestController::class, 'addToCart'])->name('cart.add');
    Route::delete('cart/{itemId}', [\App\Http\Controllers\Public\RentalRequestController::class, 'removeFromCart'])->name('cart.remove');
    
    // Checkout flow (auth required after account creation)
    Route::get('checkout', [\App\Http\Controllers\Public\RentalRequestController::class, 'checkout'])->name('checkout');
    Route::post('account/create', [\App\Http\Controllers\Public\RentalRequestController::class, 'createAccount'])->name('account.create');
    
    // Email verification
    Route::get('verify-email', [\App\Http\Controllers\Public\RentalRequestController::class, 'verifyEmail'])->name('verify-email')->middleware('auth:renter');
    Route::get('verification/verify/{id}/{hash}', [\App\Http\Controllers\Public\RentalRequestController::class, 'verify'])->name('verification.verify');
    Route::post('verification/resend', [\App\Http\Controllers\Public\RentalRequestController::class, 'resendVerification'])->name('verification.resend');
    Route::post('verification/verify-pin', [\App\Http\Controllers\Public\RentalRequestController::class, 'verifyPin'])->name('verification.verify-pin');
    
    // KYC verification (public AJAX endpoint for checkout form)
    Route::post('kyc/verify', [\App\Http\Controllers\Public\RentalRequestController::class, 'verifyKycAjax'])->name('kyc.verify');
    
    // KYC verification (authenticated)
    Route::get('kyc', [\App\Http\Controllers\Public\RentalRequestController::class, 'kyc'])->name('kyc')->middleware('auth:renter');
    Route::post('kyc', [\App\Http\Controllers\Public\RentalRequestController::class, 'kyc'])->middleware('auth:renter');
    
    // Review and submit
    Route::get('review', [\App\Http\Controllers\Public\RentalRequestController::class, 'review'])->name('review')->middleware('auth:renter');
    Route::post('review', [\App\Http\Controllers\Public\RentalRequestController::class, 'review'])->middleware('auth:renter');
    
    // Success page
    Route::get('success', [\App\Http\Controllers\Public\RentalRequestController::class, 'success'])->name('success')->middleware('auth:renter');
});

// Renter authentication routes (login now handled by business login)
Route::prefix('renter')->name('renter.')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\Renter\Auth\LoginController::class, 'logout'])->name('logout');
    
    // Protected renter routes
    Route::middleware('auth:renter')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Renter\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/rental/{rental}', [\App\Http\Controllers\Renter\DashboardController::class, 'show'])->name('dashboard.show');
    });
});

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

// Helper functions for UTF-8 sanitization in routes
if (!function_exists('sanitizeUtf8ForJson')) {
    function sanitizeUtf8ForJson(string $string): string
    {
        if (empty($string)) {
            return $string;
        }
        
        // First, try to fix encoding using mb_convert_encoding
        if (!mb_check_encoding($string, 'UTF-8')) {
            // Try to convert from various encodings
            $encodings = ['ISO-8859-1', 'Windows-1252', 'UTF-8'];
            foreach ($encodings as $encoding) {
                $converted = @mb_convert_encoding($string, 'UTF-8', $encoding);
                if (mb_check_encoding($converted, 'UTF-8')) {
                    $string = $converted;
                    break;
                }
            }
        }
        
        // Use iconv to remove invalid UTF-8 sequences
        $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
        
        // If iconv failed, use mb_convert_encoding with IGNORE flag
        if ($sanitized === false || !mb_check_encoding($sanitized, 'UTF-8')) {
            $sanitized = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        }
        
        // Remove control characters except newlines, carriage returns, and tabs
        $sanitized = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $sanitized);
        
        // Final check: ensure valid UTF-8
        if (!mb_check_encoding($sanitized, 'UTF-8')) {
            // Last resort: remove any remaining invalid bytes
            $sanitized = mb_convert_encoding($sanitized, 'UTF-8', 'UTF-8');
        }
        
        return $sanitized ?: '';
    }
}

if (!function_exists('sanitizeArrayForJson')) {
    function sanitizeArrayForJson(array $array): array
    {
        $sanitized = [];
        
        foreach ($array as $key => $value) {
            $sanitizedKey = is_string($key) ? sanitizeUtf8ForJson($key) : $key;
            
            if (is_string($value)) {
                $sanitized[$sanitizedKey] = sanitizeUtf8ForJson($value);
            } elseif (is_array($value)) {
                $sanitized[$sanitizedKey] = sanitizeArrayForJson($value);
            } else {
                $sanitized[$sanitizedKey] = $value;
            }
        }
        
        return $sanitized;
    }
}

// Direct Filesystem Email Reading Cron (PUBLIC ENDPOINT - for cron job websites)
// FIXED: Use same logic as admin dashboard button - calls the same controller method
// Reads from filesystem regardless of email account method setting
// Optional security: Add ?token=YOUR_SECRET_TOKEN to protect the endpoint
Route::get('/cron/read-emails-direct', function (\Illuminate\Http\Request $request) {
    // Increase memory limit and execution time for cron jobs
    ini_set('memory_limit', '512M');
    set_time_limit(300); // 5 minutes max
    
    // Detect if this is a cron request (minimal response needed)
    $isCronRequest = $request->hasHeader('User-Agent') && 
                     (stripos($request->header('User-Agent'), 'curl') !== false || 
                      stripos($request->header('User-Agent'), 'cron') !== false ||
                      $request->query('minimal') === 'true');
    
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
        
        try {
            $response = $controller->fetchEmailsDirect($request);
            $responseContent = $response->getContent();
        } catch (\Throwable $e) {
            // Catch any errors from controller
            \Illuminate\Support\Facades\Log::error('Error in fetchEmailsDirect controller', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing emails: ' . $e->getMessage(),
                'timestamp' => now()->toDateTimeString(),
            ], 500);
        }
        
        // Safely decode JSON, handle large responses
        $responseData = json_decode($responseContent, true);
        
        // For cron requests, return minimal response (stats only, no verbose output)
        if ($isCronRequest) {
            $executionTime = round(microtime(true) - $startTime, 2);
            
            // Extract stats from response content
            $stats = [
                'processed' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
            
            // Try to extract stats from the response content
            if (preg_match('/Total processed:\s*(\d+)/i', $responseContent, $matches)) {
                $stats['processed'] = (int)$matches[1];
            }
            if (preg_match('/Total skipped:\s*(\d+)/i', $responseContent, $matches)) {
                $stats['skipped'] = (int)$matches[1];
            }
            if (preg_match('/Total failed:\s*(\d+)/i', $responseContent, $matches)) {
                $stats['failed'] = (int)$matches[1];
            }
            
            // If responseData has stats, use those instead
            if (isset($responseData['stats']) && is_array($responseData['stats'])) {
                $stats = array_merge($stats, $responseData['stats']);
            }
            
            // Log full output for debugging (but don't return it)
            if (strlen($responseContent) > 10000) {
                \Illuminate\Support\Facades\Log::info('Cron email fetch completed (large output logged)', [
                    'stats' => $stats,
                    'execution_time' => $executionTime,
                    'output_length' => strlen($responseContent),
                ]);
            }
            
            // Return minimal response for cron
            return response()->json([
                'success' => true,
                'message' => 'Email fetching completed',
                'stats' => $stats,
                'execution_time_seconds' => $executionTime,
                'method' => 'direct_filesystem',
                'timestamp' => now()->toDateTimeString(),
            ], 200);
        }
        
        // For non-cron requests (admin dashboard), return full response
        // If JSON decode failed or response is too large, create a minimal response
        if (json_last_error() !== JSON_ERROR_NONE || strlen($responseContent) > 100000) {
            \Illuminate\Support\Facades\Log::warning('Large response detected in cron endpoint', [
                'content_length' => strlen($responseContent),
                'json_error' => json_last_error_msg(),
            ]);
            
            // Extract just the essential info from the response
            $responseData = [
                'success' => true,
                'message' => 'Email fetching completed (output truncated due to size)',
                'stats' => [
                    'processed' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                ],
            ];
            
            // Try to extract stats from the response content if possible
            if (preg_match('/Total processed:\s*(\d+)/i', $responseContent, $matches)) {
                $responseData['stats']['processed'] = (int)$matches[1];
            }
            if (preg_match('/Total skipped:\s*(\d+)/i', $responseContent, $matches)) {
                $responseData['stats']['skipped'] = (int)$matches[1];
            }
            if (preg_match('/Total failed:\s*(\d+)/i', $responseContent, $matches)) {
                $responseData['stats']['failed'] = (int)$matches[1];
            }
        }
        
        $executionTime = round(microtime(true) - $startTime, 2);
        
        // Add execution time to response
        if ($responseData) {
            $responseData['execution_time_seconds'] = $executionTime;
            $responseData['method'] = 'direct_filesystem';
            $responseData['timestamp'] = now()->toDateTimeString();
            
            // Remove or truncate large output fields for non-cron responses
            if (isset($responseData['output']) && strlen($responseData['output']) > 2000) {
                $responseData['output'] = substr($responseData['output'], 0, 2000) . "\n\n... (truncated) ...";
            }
            if (isset($responseData['summary']) && strlen($responseData['summary']) > 2000) {
                $responseData['summary'] = substr($responseData['summary'], 0, 2000) . "\n\n... (truncated) ...";
            }
            
            // Sanitize UTF-8 in all string fields before JSON encoding
            $responseData = sanitizeArrayForJson($responseData);
        }
        
        // Ensure we return a valid status code
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 500) {
            $statusCode = 200; // Don't return 500 to cron, return 200 with success=false
        }
        
        return response()->json($responseData, $statusCode);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Cron job error (Direct Filesystem)', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        // Sanitize error message before JSON encoding
        $errorMessage = sanitizeUtf8ForJson($e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $errorMessage,
            'timestamp' => now()->toDateTimeString(),
        ], 200); // Return 200 instead of 500 to prevent cron failures
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

        // OPTIMIZED: Batch load all data upfront to avoid N+1 queries
        // Reload emails after extraction to get updated data (single query)
        $emailIds = $unmatchedEmails->pluck('id')->toArray();
        
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
            // Reload emails once after all extractions (single query instead of N queries)
            $unmatchedEmails = \App\Models\ProcessedEmail::whereIn('id', $emailIds)
                ->where('is_matched', false)
                ->get();
        }
        
        // STEP 2: Parse description fields for emails that have them but missing account_number
        // This ensures account numbers are extracted before matching
        $descriptionExtractor = new \App\Services\DescriptionFieldExtractor();
        $parsedCount = 0;
        foreach ($unmatchedEmails as $processedEmail) {
            // OPTIMIZED: No refresh needed - data is already fresh from reload above
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

        // OPTIMIZED: Pre-load all payments once (single query) instead of querying per email
        $allPendingPayments = \App\Models\Payment::where('status', \App\Models\Payment::STATUS_PENDING)
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
            ->get()
            ->keyBy('id'); // Key by ID for O(1) lookup
        
        // Strategy: For each unmatched email, try to match against all pending payments
        // FIXED: Use same approach as admin checkMatch - use matchPayment() directly instead of matchEmail()
        foreach ($unmatchedEmails as $processedEmail) {
            try {
                // OPTIMIZED: No refresh needed - check is_matched from database query
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

                // OPTIMIZED: Filter pre-loaded payments instead of querying database
                $emailDate = $processedEmail->email_date ? \Carbon\Carbon::parse($processedEmail->email_date) : null;
                
                // CRITICAL: Only check payments created BEFORE email was received
                // Filter from pre-loaded collection instead of querying
                $potentialPayments = $allPendingPayments->filter(function ($payment) use ($extractedInfo, $emailDate) {
                    // Amount match (within 1 naira tolerance)
                    $amountMatch = abs($payment->amount - $extractedInfo['amount']) <= 1;
                    
                    // Time constraint: Payment must be created BEFORE email
                    $timeMatch = !$emailDate || $payment->created_at <= $emailDate;
                    
                    // Not expired
                    $notExpired = !$payment->expires_at || $payment->expires_at > now();
                    
                    return $amountMatch && $timeMatch && $notExpired;
                })->sortByDesc('created_at')->values(); // Sort by newest first
                
                // Use same logic as PaymentController::checkMatch (which works!)
                $matchLogger = new \App\Services\MatchAttemptLogger();
                $matchedPayment = null;
                
                foreach ($potentialPayments as $potentialPayment) {
                    // Use matchPayment() directly (same as PaymentController::checkMatch)
                    $match = $matchingService->matchPayment($potentialPayment, $extractedInfo, $emailDate);

                    // Log match attempt (same as PaymentController::checkMatch)
                    try {
                        $extractionMethod = null;
                        if (is_array($extractionResult) && isset($extractionResult['method'])) {
                            $extractionMethod = $extractionResult['method'];
                        }
                        
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
                        \Illuminate\Support\Facades\Log::error('Failed to log match attempt in global match cron', [
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

                    // Update business balance with charges (same as PaymentController::checkMatch)
                    if ($matchedPayment->business_id) {
                        $matchedPayment->business->incrementBalanceWithCharges($matchedPayment->amount, $matchedPayment);
                        $matchedPayment->business->refresh();
                        
                        // Send new deposit notification
                        $matchedPayment->business->notify(new \App\Notifications\NewDepositNotification($matchedPayment));
                        
                        // Check for auto-withdrawal
                        $matchedPayment->business->triggerAutoWithdrawal();
                    }

                    // CRITICAL: Reload payment with business websites relationship before dispatching webhook
                    // This ensures webhooks are sent to ALL websites under the business (e.g., fadded.net)
                    $matchedPayment->refresh();
                    $matchedPayment->load(['business.websites', 'website']);

                    // Dispatch event to send webhook (same as PaymentController::checkMatch)
                    // This will send webhooks to ALL websites under the business
                    event(new \App\Events\PaymentApproved($matchedPayment));
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

        // OPTIMIZED: Pre-load all unmatched emails once (already loaded above, but filter unmatched)
        $unmatchedEmailsByAmount = $unmatchedEmails->groupBy(function ($email) {
            return round($email->amount);
        });
        
        // Also check pending payments that weren't matched in the first pass
        foreach ($pendingPayments as $payment) {
            try {
                // OPTIMIZED: No refresh needed - check status from loaded data
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
                
                // OPTIMIZED: Filter from pre-loaded emails instead of querying database
                $amountKey = round($payment->amount);
                $potentialEmails = ($unmatchedEmailsByAmount[$amountKey] ?? collect())
                    ->filter(function ($email) use ($payment, $checkUntil) {
                        // Amount match (already filtered by groupBy)
                        $amountMatch = abs($email->amount - $payment->amount) <= 1;
                        
                        // Time constraints
                        $timeMatch = $email->email_date 
                            && $email->email_date >= $payment->created_at 
                            && $email->email_date <= $checkUntil;
                        
                        // Not matched
                        $notMatched = !$email->is_matched;
                        
                        return $amountMatch && $timeMatch && $notMatched;
                    });

                foreach ($potentialEmails as $processedEmail) {
                    try {
                        // OPTIMIZED: No refresh needed - already filtered unmatched emails
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
                        
                        // Use matchPayment() directly (same as PaymentController::checkMatch)
                        $emailDate = $processedEmail->email_date ? \Carbon\Carbon::parse($processedEmail->email_date) : null;
                        $match = $matchingService->matchPayment($payment, $extractedInfo, $emailDate);
                        
                        // Log match attempt (same as PaymentController::checkMatch)
                        try {
                            $matchLogger = new \App\Services\MatchAttemptLogger();
                            $matchLogger->logAttempt([
                                'payment_id' => $payment->id,
                                'processed_email_id' => $processedEmail->id,
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
                                'email_subject' => $processedEmail->subject,
                                'email_from' => $processedEmail->from_email,
                                'email_date' => $emailDate,
                                'amount_diff' => $match['amount_diff'] ?? null,
                                'name_similarity_percent' => $match['name_similarity_percent'] ?? null,
                                'time_diff_minutes' => $match['time_diff_minutes'] ?? null,
                                'extraction_method' => $extractionMethod,
                            ]);
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to log match attempt in global match cron', [
                                'error' => $e->getMessage(),
                                'payment_id' => $payment->id,
                                'email_id' => $processedEmail->id,
                            ]);
                        }

                        if ($match['matched']) {
                            $results['matches_found']++;
                            $results['matched_payments'][] = [
                                'transaction_id' => $payment->transaction_id,
                                'payment_id' => $payment->id,
                                'email_id' => $processedEmail->id,
                                'email_subject' => $processedEmail->subject,
                                'match_reason' => $match['reason'] ?? 'Matched',
                            ];

                            // Mark email as matched (same as PaymentController::checkMatch)
                            $processedEmail->markAsMatched($payment);

                            // Approve payment (same as PaymentController::checkMatch)
                            $payment->approve([
                                'subject' => $processedEmail->subject,
                                'from' => $processedEmail->from_email,
                                'text' => $processedEmail->text_body,
                                'html' => $processedEmail->html_body,
                                'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                                'sender_name' => $processedEmail->sender_name,
                            ]);
                            
                            // Update payer_account_number if extracted (same as PaymentController::checkMatch)
                            if (isset($extractedInfo['payer_account_number']) && $extractedInfo['payer_account_number']) {
                                $payment->update(['payer_account_number' => $extractedInfo['payer_account_number']]);
                            }

                            // Update business balance with charges (same as PaymentController::checkMatch)
                            if ($payment->business_id) {
                                $payment->business->incrementBalanceWithCharges($payment->amount, $payment);
                                $payment->business->refresh();
                                
                                // Send new deposit notification
                                $payment->business->notify(new \App\Notifications\NewDepositNotification($payment));
                                
                                // Check for auto-withdrawal
                                $payment->business->triggerAutoWithdrawal();
                            }

                            // CRITICAL: Reload payment with business websites relationship before dispatching webhook
                            // This ensures webhooks are sent to ALL websites under the business (e.g., fadded.net)
                            $payment->refresh();
                            $payment->load(['business.websites', 'website']);

                            // Dispatch event to send webhook (same as PaymentController::checkMatch)
                            // This will send webhooks to ALL websites under the business
                            event(new \App\Events\PaymentApproved($payment));

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
