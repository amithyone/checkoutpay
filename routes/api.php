<?php

use App\Http\Controllers\Api\PaymentController;
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
    
    // Email webhook (for email forwarding services)
    Route::post('/webhook/email', [\App\Http\Controllers\Api\EmailWebhookController::class, 'receive']);
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'service' => 'Email Payment Gateway',
    ]);
});
