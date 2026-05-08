<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DesktopTelemetryController;

// Add these in routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/desktop/events/batch', [DesktopTelemetryController::class, 'ingestBatch']);
    Route::get('/desktop/policy', [DesktopTelemetryController::class, 'getPolicy']);
});

