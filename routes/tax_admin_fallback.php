<?php

/**
 * Same NigTax admin API as routes/api.php, but without the global "api" prefix.
 * Use when the web server forwards paths like /v1/tax-admin/... (strip-prefix for /api).
 */

use Illuminate\Support\Facades\Route;

Route::prefix('v1/tax-admin')->group(function () {
    Route::get('/login', function () {
        return response()->json([
            'message' => 'Use POST with JSON body: {"email":"...","password":"..."}. Roles allowed: tax, super_admin.',
        ]);
    });
    Route::post('/login', [\App\Http\Controllers\Api\TaxAdminAuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::middleware(['auth:sanctum', 'tax_admin_api'])->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Api\TaxAdminAuthController::class, 'logout']);
        Route::get('/user', [\App\Http\Controllers\Api\TaxAdminAuthController::class, 'user']);
        Route::get('/stats', [\App\Http\Controllers\Api\TaxAdminStatsController::class, 'index']);
        Route::put('/password', [\App\Http\Controllers\Api\TaxAdminAuthController::class, 'changePassword']);
        Route::get('/business-records', [\App\Http\Controllers\Api\TaxAdminRecordController::class, 'businessRecords']);
        Route::get('/personal-records', [\App\Http\Controllers\Api\TaxAdminRecordController::class, 'personalRecords']);
        Route::get('/pro-users', [\App\Http\Controllers\Api\TaxAdminRecordController::class, 'proUsers']);

        Route::get('/certified/settings', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'certifiedSettings']);
        Route::put('/certified/settings', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'certifiedSettingsUpdate']);
        Route::get('/certified/consultants', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'consultantsIndex']);
        Route::post('/certified/consultants', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'consultantsStore']);
        Route::put('/certified/consultants/{id}', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'consultantsUpdate'])
            ->whereNumber('id');
        Route::delete('/certified/consultants/{id}', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'consultantsDestroy'])
            ->whereNumber('id');
        Route::post('/certified/consultants/{id}/signature', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'uploadConsultantSignature'])
            ->whereNumber('id');
        Route::post('/certified/consultants/{id}/stamp', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'uploadConsultantStamp'])
            ->whereNumber('id');
        Route::get('/certified/consultant', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'consultantShow']);
        Route::put('/certified/consultant', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'consultantUpdate']);
        Route::post('/certified/consultant/signature', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'uploadSignature']);
        Route::post('/certified/consultant/stamp', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'uploadStamp']);
        Route::get('/certified/orders', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'ordersIndex']);
        Route::get('/certified/orders/{id}', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'orderShow'])
            ->whereNumber('id');
        Route::patch('/certified/orders/{id}', [\App\Http\Controllers\Api\TaxAdminCertifiedController::class, 'orderUpdate'])
            ->whereNumber('id');
    });
});
