<?php

use App\Http\Controllers\Admin\AccountNumberController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmailAccountController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    // Admin authentication routes
    Route::get('/login', [\App\Http\Controllers\Admin\Auth\LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Admin\Auth\LoginController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\Admin\Auth\LoginController::class, 'logout'])->name('logout');

    // Protected admin routes
    Route::middleware('auth:admin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Email Accounts
        Route::resource('email-accounts', EmailAccountController::class);
        Route::post('email-accounts/{emailAccount}/test-connection', [EmailAccountController::class, 'testConnection'])
            ->name('email-accounts.test-connection');

        // Account Numbers
        Route::resource('account-numbers', AccountNumberController::class);

        // Businesses
        Route::resource('businesses', BusinessController::class);
        Route::post('businesses/{business}/regenerate-api-key', [BusinessController::class, 'regenerateApiKey'])
            ->name('businesses.regenerate-api-key');

        // Payments
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

        // Withdrawals
        Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::get('withdrawals/{withdrawal}', [WithdrawalController::class, 'show'])->name('withdrawals.show');
        Route::post('withdrawals/{withdrawal}/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve');
        Route::post('withdrawals/{withdrawal}/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject');
        Route::post('withdrawals/{withdrawal}/mark-processed', [WithdrawalController::class, 'markProcessed'])->name('withdrawals.mark-processed');

        // Transaction Logs
        Route::get('transaction-logs', [\App\Http\Controllers\Admin\TransactionLogController::class, 'index'])->name('transaction-logs.index');
        Route::get('transaction-logs/{transactionId}', [\App\Http\Controllers\Admin\TransactionLogController::class, 'show'])->name('transaction-logs.show');
    });
});
