<?php

use Illuminate\Support\Facades\Route;

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
