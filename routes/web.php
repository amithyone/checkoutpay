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
