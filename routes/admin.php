<?php

use App\Http\Controllers\Admin\AccountNumberController;
use App\Http\Controllers\Admin\BankEmailTemplateController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmailAccountController;
use App\Http\Controllers\Admin\GmailAuthController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProcessedEmailController;
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

        // Processed Emails (Inbox)
        Route::get('processed-emails', [ProcessedEmailController::class, 'index'])->name('processed-emails.index');
        Route::get('processed-emails/{processedEmail}', [ProcessedEmailController::class, 'show'])->name('processed-emails.show');
        Route::post('processed-emails/{processedEmail}/check-match', [ProcessedEmailController::class, 'checkMatch'])->name('processed-emails.check-match');

        // Email Accounts
        Route::resource('email-accounts', EmailAccountController::class);
        Route::post('email-accounts/{emailAccount}/test-connection', [EmailAccountController::class, 'testConnection'])
            ->name('email-accounts.test-connection');
        
        // Gmail API Authorization
        Route::get('email-accounts/{emailAccount}/gmail/authorize', [GmailAuthController::class, 'authorize'])
            ->name('email-accounts.gmail.authorize');
        Route::get('email-accounts/{emailAccount}/gmail/callback', [GmailAuthController::class, 'callback'])
            ->name('email-accounts.gmail.callback');

        // Account Numbers
        Route::resource('account-numbers', AccountNumberController::class);

        // Businesses
        Route::resource('businesses', BusinessController::class);
        Route::post('businesses/{business}/regenerate-api-key', [BusinessController::class, 'regenerateApiKey'])
            ->name('businesses.regenerate-api-key');
        Route::post('businesses/{business}/approve-website', [BusinessController::class, 'approveWebsite'])
            ->name('businesses.approve-website');
        Route::post('businesses/{business}/reject-website', [BusinessController::class, 'rejectWebsite'])
            ->name('businesses.reject-website');
        Route::post('businesses/{business}/toggle-status', [BusinessController::class, 'toggleStatus'])
            ->name('businesses.toggle-status');
        Route::post('businesses/{business}/update-balance', [BusinessController::class, 'updateBalance'])
            ->name('businesses.update-balance');
        
        // Business KYC Management
        Route::post('businesses/{business}/verification/{verification}/approve', [BusinessController::class, 'approveVerification'])
            ->name('businesses.verification.approve');
        Route::post('businesses/{business}/verification/{verification}/reject', [BusinessController::class, 'rejectVerification'])
            ->name('businesses.verification.reject');
        Route::get('businesses/{business}/verification/{verification}/download', [BusinessController::class, 'downloadVerificationDocument'])
            ->name('businesses.verification.download');

        // Payments
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/needs-review', [PaymentController::class, 'needsReview'])->name('payments.needs-review');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/check-match', [PaymentController::class, 'checkMatch'])->name('payments.check-match');
        Route::post('payments/{payment}/manual-approve', [PaymentController::class, 'manualApprove'])->name('payments.manual-approve');
        Route::post('payments/{payment}/mark-expired', [PaymentController::class, 'markAsExpired'])->name('payments.mark-expired');
        Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

        // Withdrawals
        Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::get('withdrawals/{withdrawal}', [WithdrawalController::class, 'show'])->name('withdrawals.show');
        Route::post('withdrawals/{withdrawal}/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve');
        Route::post('withdrawals/{withdrawal}/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject');
        Route::post('withdrawals/{withdrawal}/mark-processed', [WithdrawalController::class, 'markProcessed'])->name('withdrawals.mark-processed');

        // Transaction Logs
        Route::get('transaction-logs', [\App\Http\Controllers\Admin\TransactionLogController::class, 'index'])->name('transaction-logs.index');
        Route::get('transaction-logs/{transactionId}', [\App\Http\Controllers\Admin\TransactionLogController::class, 'show'])->name('transaction-logs.show');

        // Test Transaction (Live Testing)
        Route::get('test-transaction', [\App\Http\Controllers\Admin\TestTransactionController::class, 'index'])->name('test-transaction.index');
        Route::post('test-transaction/create', [\App\Http\Controllers\Admin\TestTransactionController::class, 'createPayment'])->name('test-transaction.create');
        Route::get('test-transaction/status/{transactionId}', [\App\Http\Controllers\Admin\TestTransactionController::class, 'getStatus'])->name('test-transaction.status');
        Route::post('test-transaction/check-email', [\App\Http\Controllers\Admin\TestTransactionController::class, 'checkEmail'])->name('test-transaction.check-email');

        // Settings
        Route::get('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/whitelisted-emails', [\App\Http\Controllers\Admin\SettingsController::class, 'addWhitelistedEmail'])->name('settings.add-whitelisted-email');
        Route::delete('settings/whitelisted-emails/{whitelistedEmail}', [\App\Http\Controllers\Admin\SettingsController::class, 'removeWhitelistedEmail'])->name('settings.remove-whitelisted-email');

        // Bank Email Templates
        Route::resource('bank-email-templates', BankEmailTemplateController::class);

        // Email Monitoring
        Route::post('email-monitor/fetch', [\App\Http\Controllers\Admin\EmailMonitorController::class, 'fetchEmails'])->name('email-monitor.fetch');
        Route::post('email-monitor/fetch-direct', [\App\Http\Controllers\Admin\EmailMonitorController::class, 'fetchEmailsDirect'])->name('email-monitor.fetch-direct');
        Route::post('email-monitor/check-updates', [\App\Http\Controllers\Admin\EmailMonitorController::class, 'checkTransactionUpdates'])->name('email-monitor.check-updates');

        // Whitelisted Email Addresses
        Route::resource('whitelisted-emails', \App\Http\Controllers\Admin\WhitelistedEmailController::class);

        // Match Attempts (Match Logs)
        Route::get('match-attempts', [\App\Http\Controllers\Admin\MatchAttemptController::class, 'index'])->name('match-attempts.index');
        Route::get('match-attempts/{matchAttempt}', [\App\Http\Controllers\Admin\MatchAttemptController::class, 'show'])->name('match-attempts.show');
        Route::post('match-attempts/{matchAttempt}/retry', [\App\Http\Controllers\Admin\MatchAttemptController::class, 'retry'])->name('match-attempts.retry');
        Route::delete('match-attempts/clear', [\App\Http\Controllers\Admin\MatchAttemptController::class, 'clear'])->name('match-attempts.clear');
        Route::post('processed-emails/{processedEmail}/retry-match', [\App\Http\Controllers\Admin\MatchAttemptController::class, 'retryEmail'])->name('processed-emails.retry-match');
        Route::post('processed-emails/{processedEmail}/re-extract-match', [\App\Http\Controllers\Admin\MatchAttemptController::class, 'reExtractAndMatch'])->name('processed-emails.re-extract-match');
        
        // Global Match Trigger
        Route::post('match/trigger-global', [\App\Http\Controllers\Admin\MatchController::class, 'triggerGlobalMatch'])->name('match.trigger-global');
    });
});
