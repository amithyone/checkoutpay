<?php

use App\Http\Controllers\Admin\AccountNumberController;
use App\Http\Controllers\Admin\BankEmailTemplateController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmailAccountController;
use App\Http\Controllers\Admin\GmailAuthController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProcessedEmailController;
use App\Http\Controllers\Admin\StatsController;
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
        Route::post('/extract-missing-names', [DashboardController::class, 'extractMissingNames'])->name('extract-missing-names');
        Route::post('/test-sender-extraction', [DashboardController::class, 'testSenderExtraction'])->name('test-sender-extraction');
        
        // Statistics
        Route::get('/stats', [StatsController::class, 'index'])->name('stats.index');

        // Processed Emails (Inbox)
        Route::get('processed-emails', [ProcessedEmailController::class, 'index'])->name('processed-emails.index');
        Route::get('processed-emails/{processedEmail}', [ProcessedEmailController::class, 'show'])->name('processed-emails.show');
        Route::post('processed-emails/{processedEmail}/check-match', [ProcessedEmailController::class, 'checkMatch'])->name('processed-emails.check-match');
        Route::post('processed-emails/{processedEmail}/update-name', [ProcessedEmailController::class, 'updateName'])->name('processed-emails.update-name');
        Route::post('processed-emails/{processedEmail}/update-and-rematch', [ProcessedEmailController::class, 'updateAndRematch'])->name('processed-emails.update-and-rematch');
        Route::post('processed-emails/{processedEmail}/update-amount', [ProcessedEmailController::class, 'updateAmount'])->name('processed-emails.update-amount');
        Route::post('processed-emails/{processedEmail}/update-amount-and-rematch', [ProcessedEmailController::class, 'updateAmountAndRematch'])->name('processed-emails.update-amount-and-rematch');
        Route::get('processed-emails/{processedEmail}/pending-payments', [ProcessedEmailController::class, 'getPendingPayments'])->name('processed-emails.pending-payments');
        Route::post('processed-emails/{processedEmail}/match-to-payment', [ProcessedEmailController::class, 'matchToPayment'])->name('processed-emails.match-to-payment');

        // Email Accounts (Admin/Super Admin only)
        Route::middleware('admin_or_super')->group(function () {
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
            Route::post('account-numbers/validate-account', [AccountNumberController::class, 'validateAccount'])
                ->name('account-numbers.validate-account');
        });

        // Businesses
        Route::resource('businesses', BusinessController::class);
        Route::post('businesses/{business}/regenerate-api-key', [BusinessController::class, 'regenerateApiKey'])
            ->name('businesses.regenerate-api-key');
        Route::post('businesses/{business}/approve-website', [BusinessController::class, 'approveWebsite'])
            ->name('businesses.approve-website');
        Route::post('businesses/{business}/reject-website', [BusinessController::class, 'rejectWebsite'])
            ->name('businesses.reject-website');
        Route::post('businesses/{business}/add-website', [BusinessController::class, 'addWebsite'])
            ->name('businesses.add-website');
        Route::put('businesses/{business}/websites/{website}', [BusinessController::class, 'updateWebsite'])
            ->name('businesses.update-website');
        Route::delete('businesses/{business}/websites/{website}', [BusinessController::class, 'deleteWebsite'])
            ->name('businesses.delete-website');
        Route::get('businesses/{business}/websites/{website}/transactions/preview', [BusinessController::class, 'previewTransactions'])
            ->middleware('super_admin')
            ->name('businesses.websites.preview-transactions');
        Route::post('businesses/{business}/websites/{website}/transfer-transactions', [BusinessController::class, 'transferTransactions'])
            ->middleware('super_admin')
            ->name('businesses.websites.transfer-transactions');
        Route::post('businesses/{business}/transfer-transactions', [BusinessController::class, 'transferTransactions'])
            ->middleware('super_admin')
            ->name('businesses.transfer-transactions');
        Route::post('businesses/{business}/websites/{website}/toggle-charges', [BusinessController::class, 'toggleWebsiteCharges'])
            ->middleware('super_admin')
            ->name('businesses.websites.toggle-charges');
        Route::post('businesses/{business}/toggle-status', [BusinessController::class, 'toggleStatus'])
            ->name('businesses.toggle-status');
        Route::post('businesses/{business}/update-balance', [BusinessController::class, 'updateBalance'])
            ->middleware('super_admin')
            ->name('businesses.update-balance');
        Route::post('businesses/{business}/update-charges', [BusinessController::class, 'updateCharges'])
            ->middleware('super_admin')
            ->name('businesses.update-charges');
        Route::post('businesses/{business}/login-as', [BusinessController::class, 'loginAsBusiness'])
            ->middleware('super_admin')
            ->name('businesses.login-as');
        Route::post('businesses/exit-impersonation', [BusinessController::class, 'exitImpersonation'])
            ->name('businesses.exit-impersonation');
        
        // Business KYC Management
        Route::post('businesses/{business}/verifications/{verification}/approve', [BusinessController::class, 'approveVerification'])
            ->name('businesses.verification.approve');
        Route::post('businesses/{business}/verifications/{verification}/reject', [BusinessController::class, 'rejectVerification'])
            ->name('businesses.verification.reject');
        Route::get('businesses/{business}/verifications/{verification}/download', [BusinessController::class, 'downloadVerificationDocument'])
            ->name('businesses.verification.download');

        // Payments
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/needs-review', [PaymentController::class, 'needsReview'])->name('payments.needs-review');
        Route::get('payments/expired', [PaymentController::class, 'expired'])->name('payments.expired');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/check-match', [PaymentController::class, 'checkMatch'])->name('payments.check-match');
        Route::post('payments/{payment}/manual-verify', [PaymentController::class, 'manualVerify'])->name('payments.manual-verify');
        Route::post('payments/{payment}/manual-approve', [PaymentController::class, 'manualApprove'])->name('payments.manual-approve');
        Route::get('payments/{payment}/unmatched-emails', [PaymentController::class, 'getUnmatchedEmails'])->name('payments.unmatched-emails');
        Route::post('payments/{payment}/mark-expired', [PaymentController::class, 'markAsExpired'])->name('payments.mark-expired');
        Route::post('payments/{payment}/resend-webhook', [PaymentController::class, 'resendWebhook'])->name('payments.resend-webhook');
        Route::post('payments/resend-webhooks-bulk', [PaymentController::class, 'resendWebhooksBulk'])->name('payments.resend-webhooks-bulk');
        Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

        // Invoices
        Route::resource('invoices', \App\Http\Controllers\Admin\InvoiceController::class);
        Route::get('invoices/{invoice}/pdf', [\App\Http\Controllers\Admin\InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        Route::get('invoices/{invoice}/view-pdf', [\App\Http\Controllers\Admin\InvoiceController::class, 'viewPdf'])->name('invoices.view-pdf');
        Route::post('invoices/{invoice}/send', [\App\Http\Controllers\Admin\InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('invoices/{invoice}/mark-paid', [\App\Http\Controllers\Admin\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');

        // Charity / GoFund campaigns
        Route::resource('charity', \App\Http\Controllers\Admin\CharityController::class)->parameters(['charity' => 'campaign'])->names('charity');

        // Withdrawals
        Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::get('withdrawals/create', [WithdrawalController::class, 'create'])->name('withdrawals.create');
        Route::post('withdrawals', [WithdrawalController::class, 'store'])->name('withdrawals.store');
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

        // Settings (Admin/Super Admin only)
        Route::middleware('admin_or_super')->group(function () {
            Route::get('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
            Route::put('settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');
            Route::post('settings/general', [\App\Http\Controllers\Admin\SettingsController::class, 'updateGeneral'])->name('settings.update-general');
            Route::post('settings/whitelisted-emails', [\App\Http\Controllers\Admin\SettingsController::class, 'addWhitelistedEmail'])->name('settings.add-whitelisted-email');
            Route::delete('settings/whitelisted-emails/{whitelistedEmail}', [\App\Http\Controllers\Admin\SettingsController::class, 'removeWhitelistedEmail'])->name('settings.remove-whitelisted-email');
            
            // Email Templates
            Route::get('email-templates', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'index'])->name('email-templates.index');
            Route::get('email-templates/{template}/edit', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'edit'])->name('email-templates.edit');
            Route::put('email-templates/{template}', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'update'])->name('email-templates.update');
            Route::post('email-templates/{template}/reset', [\App\Http\Controllers\Admin\EmailTemplateController::class, 'reset'])->name('email-templates.reset');
        });

        // Pages Management
        Route::resource('pages', \App\Http\Controllers\Admin\PageController::class);

        // Support Tickets (Live Chat Style)
        Route::get('support', [\App\Http\Controllers\Admin\SupportController::class, 'index'])->name('support.index');
        Route::get('support/{ticket}', [\App\Http\Controllers\Admin\SupportController::class, 'show'])->name('support.show');
        Route::post('support/{ticket}/reply', [\App\Http\Controllers\Admin\SupportController::class, 'reply'])->name('support.reply');
        Route::post('support/{ticket}/update-status', [\App\Http\Controllers\Admin\SupportController::class, 'updateStatus'])->name('support.update-status');
        Route::post('support/{ticket}/assign', [\App\Http\Controllers\Admin\SupportController::class, 'assign'])->name('support.assign');

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

        // Staff Management (Super Admin only)
        Route::middleware('super_admin')->group(function () {
            Route::resource('staff', \App\Http\Controllers\Admin\StaffController::class);
            Route::post('staff/{staff}/toggle-status', [\App\Http\Controllers\Admin\StaffController::class, 'toggleStatus'])
                ->name('staff.toggle-status');
        });

        // Profile Management (Super Admin only)
        Route::middleware('super_admin')->group(function () {
            Route::get('profile', [\App\Http\Controllers\Admin\ProfileController::class, 'index'])->name('profile.index');
            Route::put('profile', [\App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('profile.update');
            Route::put('profile/password', [\App\Http\Controllers\Admin\ProfileController::class, 'updatePassword'])->name('profile.update-password');
        });

        // Tickets Management
        Route::prefix('tickets')->name('tickets.')->group(function () {
            Route::get('events', [\App\Http\Controllers\Admin\TicketController::class, 'events'])->name('events.index');
            Route::get('orders', [\App\Http\Controllers\Admin\TicketController::class, 'orders'])->name('orders.index');
            Route::get('orders/{order}', [\App\Http\Controllers\Admin\TicketController::class, 'showOrder'])->name('orders.show');
            Route::post('orders/{order}/refund', [\App\Http\Controllers\Admin\TicketController::class, 'refund'])->name('orders.refund');
            Route::put('events/{event}/max-tickets', [\App\Http\Controllers\Admin\TicketController::class, 'updateMaxTickets'])->name('events.update-max-tickets');
            
            // QR Scanner
            Route::get('scanner', [\App\Http\Controllers\Admin\TicketScannerController::class, 'index'])->name('scanner');
            Route::post('scanner/verify', [\App\Http\Controllers\Admin\TicketScannerController::class, 'verify'])->name('scanner.verify');
            Route::post('scanner/check-in', [\App\Http\Controllers\Admin\TicketScannerController::class, 'checkIn'])->name('scanner.check-in');
            Route::post('scanner/manual-check-in', [\App\Http\Controllers\Admin\TicketScannerController::class, 'manualCheckIn'])->name('scanner.manual-check-in');
        });

        // Memberships Management
        Route::prefix('memberships')->name('memberships.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\MembershipController::class, 'index'])->name('index');
            Route::get('{membership}', [\App\Http\Controllers\Admin\MembershipController::class, 'show'])->name('show');
            Route::post('{membership}/status', [\App\Http\Controllers\Admin\MembershipController::class, 'updateStatus'])->name('update-status');
            Route::delete('{membership}', [\App\Http\Controllers\Admin\MembershipController::class, 'destroy'])->name('destroy');
        });
        Route::resource('membership-categories', \App\Http\Controllers\Admin\MembershipCategoryController::class);

        // Rentals Management
        Route::prefix('rentals')->name('rentals.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\RentalController::class, 'index'])->name('index');
            Route::get('/{rental}', [\App\Http\Controllers\Admin\RentalController::class, 'show'])->name('show');
            Route::post('/{rental}/update-status', [\App\Http\Controllers\Admin\RentalController::class, 'updateStatus'])->name('update-status');
            Route::delete('/{rental}', [\App\Http\Controllers\Admin\RentalController::class, 'destroy'])->name('destroy');
        });

        // Rental Categories Management
        Route::resource('rental-categories', \App\Http\Controllers\Admin\RentalCategoryController::class);

        // Rental Items Management
        Route::resource('rental-items', \App\Http\Controllers\Admin\RentalItemController::class);
    });
});
