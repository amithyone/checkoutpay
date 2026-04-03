<?php

/**
 * Public NigTax routes without /api prefix (when nginx strips /api before PHP).
 */

use Illuminate\Support\Facades\Route;

Route::post('v1/nigtax/visit', [\App\Http\Controllers\Api\NigtaxVisitController::class, 'store'])
    ->middleware('throttle:120,1');
