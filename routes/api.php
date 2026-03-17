<?php

use App\Http\Controllers\Api\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->middleware(\App\Http\Middleware\AuthenticateApiKey::class)->group(function () {
    // Payment routes (require API key)
    Route::post('/payment-request', [PaymentController::class, 'store']);
    Route::get('/payment/{transactionId}', [PaymentController::class, 'show']);
    // Correct wrong amount: updates payment, recalculates charges, dispatches CheckPaymentEmails to re-scan
    // emails with new amount; when an email matches, payment is approved and the same webhook is sent
    // (payment.approved, unchanged payload). Only pending, non-expired payments can be updated.
    Route::patch('/payment/{transactionId}/amount', [PaymentController::class, 'updateAmount']);
    Route::get('/payments', [PaymentController::class, 'index']);
    
    // Withdrawal routes (require API key)
    Route::post('/withdrawal', [\App\Http\Controllers\Api\WithdrawalController::class, 'store']);
    Route::get('/withdrawals', [\App\Http\Controllers\Api\WithdrawalController::class, 'index']);
    Route::get('/balance', [\App\Http\Controllers\Api\WithdrawalController::class, 'balance']);
});

// Public routes (no API key required)
Route::prefix('v1')->group(function () {
    // Statistics routes
    Route::get('/statistics', [\App\Http\Controllers\Api\StatisticsController::class, 'index']);
    
    // Email webhook (for email forwarding services like Zapier)
    Route::post('/email/webhook', [\App\Http\Controllers\Api\EmailWebhookController::class, 'receive']);
    Route::get('/email/webhook', [\App\Http\Controllers\Api\EmailWebhookController::class, 'healthCheck']); // GET for health checks and testing
    Route::post('/webhook/email', [\App\Http\Controllers\Api\EmailWebhookController::class, 'receive']); // Legacy route for backward compatibility
    Route::get('/webhook/email', [\App\Http\Controllers\Api\EmailWebhookController::class, 'healthCheck']); // GET for legacy route too
    Route::get('/email/webhook/health', [\App\Http\Controllers\Api\EmailWebhookController::class, 'healthCheck'])->name('email.webhook.health');
    
    // Transaction check endpoint (for external sites to trigger email checking)
    Route::post('/transaction/check', [\App\Http\Controllers\Api\TransactionCheckController::class, 'checkTransaction']);
    Route::get('/transaction/check', [\App\Http\Controllers\Api\TransactionCheckController::class, 'checkTransaction']); // Also support GET
    
    // Webhook processing cron endpoint (for external cron services)
    Route::get('/cron/process-webhooks', [\App\Http\Controllers\Cron\WebhookCronController::class, 'processWebhooks']);
    Route::post('/cron/process-webhooks', [\App\Http\Controllers\Cron\WebhookCronController::class, 'processWebhooks']); // Also support POST

    /**
     * Rentals public API (no auth required)
     */
    Route::prefix('rentals')->group(function () {
        // Catalog
        Route::get('items', [\App\Http\Controllers\Api\Rentals\ItemController::class, 'index']);
        Route::get('items/{slug}', [\App\Http\Controllers\Api\Rentals\ItemController::class, 'show']);
        Route::get('items/{id}/unavailable-dates', [\App\Http\Controllers\Api\Rentals\ItemController::class, 'unavailableDates'])
            ->whereNumber('id');

        // KYC verification (public AJAX-style endpoint)
        Route::post('kyc/verify', [\App\Http\Controllers\Api\Rentals\KycController::class, 'verify']);
        // Dynamic possible banks for an account number (NUBAN-backed)
        Route::post('kyc/banks', [\App\Http\Controllers\Api\Rentals\KycController::class, 'banksForAccount']);
        // All known banks from Checkout DB (cached from NUBAN responses)
        Route::get('banks', [\App\Http\Controllers\Api\Rentals\KycController::class, 'banksFromDatabase']);

        // Renter auth (token-based)
        Route::post('auth/register', [\App\Http\Controllers\Api\Rentals\AuthController::class, 'register']);
        Route::post('auth/login', [\App\Http\Controllers\Api\Rentals\AuthController::class, 'login']);

        // Forgot password – send reset link email
        Route::post('password/email', [\App\Http\Controllers\Api\Rentals\PasswordResetController::class, 'sendResetLinkEmail']);
    });
});

/**
 * Rentals authenticated API (requires Sanctum token, authenticating Renter model)
 */
Route::prefix('v1/rentals')
    ->middleware('auth:sanctum')
    ->group(function () {
        // Current renter
        Route::get('me', [\App\Http\Controllers\Api\Rentals\AuthController::class, 'me']);
        Route::post('auth/logout', [\App\Http\Controllers\Api\Rentals\AuthController::class, 'logout']);

        // KYC update for renter
        Route::post('me/kyc', [\App\Http\Controllers\Api\Rentals\KycController::class, 'update']);
        Route::post('me/kyc-id', [\App\Http\Controllers\Api\Rentals\KycController::class, 'uploadId']);

        // Checkout flow
        Route::post('checkout/quote', [\App\Http\Controllers\Api\Rentals\CheckoutController::class, 'quote']);
        Route::post('checkout/submit', [\App\Http\Controllers\Api\Rentals\CheckoutController::class, 'submit']);

        // Account management
        Route::post('password/change', [\App\Http\Controllers\Api\Rentals\AccountController::class, 'changePassword']);
        Route::get('wallet', [\App\Http\Controllers\Api\Rentals\AccountController::class, 'wallet']);

        // Renter rentals
        Route::get('requests', [\App\Http\Controllers\Api\Rentals\CheckoutController::class, 'listRentals']);
        Route::get('requests/{rental}', [\App\Http\Controllers\Api\Rentals\CheckoutController::class, 'showRental'])
            ->whereNumber('rental');
    });

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'service' => 'Email Payment Gateway',
    ]);
});
