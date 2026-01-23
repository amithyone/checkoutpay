<?php

use App\Http\Controllers\Admin\AccountNumberController;
use App\Http\Controllers\Admin\BankEmailTemplateController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmailAccountController;
use App\Http\Controllers\Admin\MatchAttemptController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProcessedEmailController;
use App\Http\Controllers\Admin\TestTransactionController;
use App\Http\Controllers\Admin\TransactionLogController;
use App\Http\Controllers\Admin\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application.
|
*/

Route::middleware(['web', 'auth:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Account Numbers
    Route::resource('account-numbers', AccountNumberController::class);
    Route::post('account-numbers/validate-account', [AccountNumberController::class, 'validateAccount'])->name('account-numbers.validate-account');

    // Email Accounts
    Route::resource('email-accounts', EmailAccountController::class);
    Route::post('email-accounts/{emailAccount}/test-connection', [EmailAccountController::class, 'testConnection'])->name('email-accounts.test-connection');

    // Processed Emails (Inbox)
    Route::get('processed-emails', [ProcessedEmailController::class, 'index'])->name('processed-emails.index');
    Route::get('processed-emails/{processedEmail}', [ProcessedEmailController::class, 'show'])->name('processed-emails.show');
    Route::put('processed-emails/{processedEmail}/name', [ProcessedEmailController::class, 'updateName'])->name('processed-emails.update-name');
    Route::post('processed-emails/{processedEmail}/update-and-rematch', [ProcessedEmailController::class, 'updateAndRematch'])->name('processed-emails.update-and-rematch');

    // Payments
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/needs-review', [PaymentController::class, 'needsReview'])->name('payments.needs-review');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('payments/{payment}/approve', [PaymentController::class, 'approve'])->name('payments.approve');
    Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');

    // Withdrawals
    Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::get('withdrawals/{withdrawal}', [WithdrawalController::class, 'show'])->name('withdrawals.show');
    Route::post('withdrawals/{withdrawal}/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve');
    Route::post('withdrawals/{withdrawal}/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject');

    // Transaction Logs
    Route::get('transaction-logs', [TransactionLogController::class, 'index'])->name('transaction-logs.index');
    Route::get('transaction-logs/{transactionLog}', [TransactionLogController::class, 'show'])->name('transaction-logs.show');

    // Test Transaction
    Route::get('test-transaction', [TestTransactionController::class, 'index'])->name('test-transaction.index');
    Route::post('test-transaction', [TestTransactionController::class, 'createPayment'])->name('test-transaction.create');

    // Bank Email Templates
    Route::resource('bank-email-templates', BankEmailTemplateController::class);

    // Match Attempts
    Route::get('match-attempts', [MatchAttemptController::class, 'index'])->name('match-attempts.index');
    Route::get('match-attempts/{matchAttempt}', [MatchAttemptController::class, 'show'])->name('match-attempts.show');

    // Businesses
    Route::resource('businesses', BusinessController::class);
});
