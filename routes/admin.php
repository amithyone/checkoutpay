<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\BusinessController;

Route::prefix('admin')->name('admin.')->middleware(['auth:admin'])->group(function () {
    // Business Management
    Route::resource('businesses', BusinessController::class);
    Route::post('businesses/{business}/toggle-status', [BusinessController::class, 'toggleStatus'])->name('businesses.toggle-status');
    Route::post('businesses/{business}/regenerate-api-key', [BusinessController::class, 'regenerateApiKey'])->name('businesses.regenerate-api-key');
    Route::post('businesses/{business}/approve-website', [BusinessController::class, 'approveWebsite'])->name('businesses.approve-website');
    Route::post('businesses/{business}/reject-website', [BusinessController::class, 'rejectWebsite'])->name('businesses.reject-website');
    Route::post('businesses/{business}/add-website', [BusinessController::class, 'addWebsite'])->name('businesses.add-website');
    Route::delete('businesses/{business}/websites/{website}', [BusinessController::class, 'deleteWebsite'])->name('businesses.delete-website');
    Route::post('businesses/{business}/update-balance', [BusinessController::class, 'updateBalance'])->name('businesses.update-balance');
    
    // KYC/Verification Management
    Route::post('businesses/{business}/verifications/{verification}/approve', [BusinessController::class, 'approveVerification'])->name('businesses.verification.approve');
    Route::post('businesses/{business}/verifications/{verification}/reject', [BusinessController::class, 'rejectVerification'])->name('businesses.verification.reject');
    Route::get('businesses/{business}/verifications/{verification}/download', [BusinessController::class, 'downloadVerificationDocument'])->name('businesses.verification.download');
});
