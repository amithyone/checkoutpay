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
