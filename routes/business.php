<?php

use App\Http\Controllers\Business\Auth\LoginController;
use App\Http\Controllers\Business\Auth\RegisterController;
use App\Http\Controllers\Business\DashboardController;
use App\Http\Controllers\Business\TransactionController;
use App\Http\Controllers\Business\WithdrawalController;
use App\Http\Controllers\Business\StatisticsController;
use App\Http\Controllers\Business\ProfileController;
use App\Http\Controllers\Business\TeamController;
use App\Http\Controllers\Business\SettingsController;
use App\Http\Controllers\Business\KeysController;
use App\Http\Controllers\Business\WebsitesController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')->name('business.')->group(function () {
    // Business authentication routes
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Password reset routes
    Route::get('/password/reset', [\App\Http\Controllers\Business\Auth\ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/password/email', [\App\Http\Controllers\Business\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/password/reset/{token}', [\App\Http\Controllers\Business\Auth\ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/password/reset', [\App\Http\Controllers\Business\Auth\ResetPasswordController::class, 'reset'])->name('password.update');

    // Email verification routes
    Route::get('/email/verify', [\App\Http\Controllers\Business\Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\Business\Auth\EmailVerificationController::class, 'verify'])->name('verification.verify');
    Route::post('/email/verify-pin', [\App\Http\Controllers\Business\Auth\EmailVerificationController::class, 'verifyPin'])->name('verification.verify-pin');
    Route::post('/email/verification-notification', [\App\Http\Controllers\Business\Auth\EmailVerificationController::class, 'resend'])->name('verification.send');
    Route::post('/email/resend-verification', [\App\Http\Controllers\Business\Auth\EmailVerificationController::class, 'resendWithoutAuth'])->name('verification.resend-without-auth');

    // Two-Factor Authentication routes
    Route::get('/2fa/verify', [\App\Http\Controllers\Business\Auth\TwoFactorController::class, 'showVerifyForm'])->name('2fa.verify');
    Route::post('/2fa/verify', [\App\Http\Controllers\Business\Auth\TwoFactorController::class, 'verify'])->name('2fa.verify.post');

    // Protected business routes
    Route::middleware([\App\Http\Middleware\AllowAdminImpersonation::class, 'auth:business'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Transactions
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');

        // Withdrawals
        Route::get('/withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::get('/withdrawals/create', [WithdrawalController::class, 'create'])->name('withdrawals.create');
        Route::post('/withdrawals', [WithdrawalController::class, 'store'])->name('withdrawals.store');
        Route::get('/withdrawals/{withdrawal}', [WithdrawalController::class, 'show'])->name('withdrawals.show');
        Route::post('/withdrawals/validate-account', [WithdrawalController::class, 'validateAccount'])->name('withdrawals.validate-account');
        Route::post('/withdrawals/save-account', [WithdrawalController::class, 'saveAccount'])->name('withdrawals.save-account');
        Route::put('/withdrawals/auto-withdraw-settings', [WithdrawalController::class, 'updateAutoWithdrawSettings'])->name('withdrawals.auto-withdraw-settings');
        Route::delete('/withdrawals/accounts/{account}', [WithdrawalController::class, 'deleteAccount'])->name('withdrawals.delete-account');

        // Statistics
        Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics.index');

        // Business Profile
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.update-password');

        // Team
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');

        // Settings
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

        // Memberships
        Route::resource('memberships', \App\Http\Controllers\Business\MembershipController::class);

        // Rentals
        Route::prefix('rentals')->name('rentals.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Business\RentalController::class, 'index'])->name('index');
            Route::get('/items', [\App\Http\Controllers\Business\RentalController::class, 'items'])->name('items');
            Route::get('/items/create', [\App\Http\Controllers\Business\RentalController::class, 'createItem'])->name('items.create');
            Route::post('/items', [\App\Http\Controllers\Business\RentalController::class, 'storeItem'])->name('items.store');
            Route::get('/items/{item}/edit', [\App\Http\Controllers\Business\RentalController::class, 'editItem'])->name('items.edit');
            Route::put('/items/{item}', [\App\Http\Controllers\Business\RentalController::class, 'updateItem'])->name('items.update');
            Route::patch('/items/{item}/daily-rate', [\App\Http\Controllers\Business\RentalController::class, 'updateDailyRate'])->name('items.update-daily-rate');
            Route::post('/items/{item}/photo', [\App\Http\Controllers\Business\RentalController::class, 'addItemPhoto'])->name('items.add-photo');
            Route::delete('/items/{item}', [\App\Http\Controllers\Business\RentalController::class, 'deleteItem'])->name('items.destroy');
            Route::get('/{rental}', [\App\Http\Controllers\Business\RentalController::class, 'show'])->name('show');
            Route::post('/{rental}/approve', [\App\Http\Controllers\Business\RentalController::class, 'approve'])->name('approve');
            Route::post('/{rental}/reject', [\App\Http\Controllers\Business\RentalController::class, 'reject'])->name('reject');
            Route::post('/{rental}/update-status', [\App\Http\Controllers\Business\RentalController::class, 'updateStatus'])->name('update-status');
        });
        Route::post('/settings/regenerate-api-key', [SettingsController::class, 'regenerateApiKey'])->name('settings.regenerate-api-key');
        Route::delete('/settings/profile-picture', [SettingsController::class, 'removeProfilePicture'])->name('settings.remove-profile-picture');
        Route::get('/settings/2fa/setup', [SettingsController::class, 'setupTwoFactor'])->name('settings.2fa.setup');
        Route::post('/settings/2fa/verify', [SettingsController::class, 'verifyTwoFactorSetup'])->name('settings.2fa.verify');
        Route::post('/settings/2fa/disable', [SettingsController::class, 'disableTwoFactor'])->name('settings.2fa.disable');

        // API Keys & Integration
        Route::get('/keys', [KeysController::class, 'index'])->name('keys.index');
        Route::post('/keys/request-account-number', [KeysController::class, 'requestAccountNumber'])->name('keys.request-account-number');
        Route::get('/api-documentation', [\App\Http\Controllers\Business\ApiDocumentationController::class, 'index'])->name('api-documentation.index');

        // Websites Management
        Route::get('/websites', [WebsitesController::class, 'index'])->name('websites.index');
        Route::post('/websites', [WebsitesController::class, 'store'])->name('websites.store');
        Route::put('/websites/{website}', [WebsitesController::class, 'update'])->name('websites.update');
        Route::delete('/websites/{website}', [WebsitesController::class, 'destroy'])->name('websites.destroy');

        // Verification/KYC
        Route::get('/verification', [\App\Http\Controllers\Business\VerificationController::class, 'index'])->name('verification.index');
        Route::post('/verification', [\App\Http\Controllers\Business\VerificationController::class, 'store'])->name('verification.store');

        // Invoices
        Route::resource('invoices', \App\Http\Controllers\Business\InvoiceController::class);
        Route::post('invoices/{invoice}/send', [\App\Http\Controllers\Business\InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('invoices/{invoice}/mark-paid', [\App\Http\Controllers\Business\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
        Route::get('invoices/{invoice}/pdf', [\App\Http\Controllers\Business\InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        Route::get('invoices/{invoice}/view-pdf', [\App\Http\Controllers\Business\InvoiceController::class, 'viewPdf'])->name('invoices.view-pdf');
        Route::get('/verification/{verification}/download', [\App\Http\Controllers\Business\VerificationController::class, 'download'])->name('verification.download');

        // Charity / GoFund campaigns
        Route::get('charity', [\App\Http\Controllers\Business\CharityController::class, 'index'])->name('charity.index');
        Route::get('charity/create', [\App\Http\Controllers\Business\CharityController::class, 'create'])->name('charity.create');
        Route::post('charity', [\App\Http\Controllers\Business\CharityController::class, 'store'])->name('charity.store');
        Route::get('charity/{campaign}/edit', [\App\Http\Controllers\Business\CharityController::class, 'edit'])->name('charity.edit');
        Route::put('charity/{campaign}', [\App\Http\Controllers\Business\CharityController::class, 'update'])->name('charity.update');

        // Activity Logs
        Route::get('/activity', [\App\Http\Controllers\Business\ActivityLogController::class, 'index'])->name('activity.index');

        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Business\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Business\NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [\App\Http\Controllers\Business\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::get('/notifications/unread-count', [\App\Http\Controllers\Business\NotificationController::class, 'unreadCount'])->name('notifications.unread-count');

        // Support
        Route::get('/support', [\App\Http\Controllers\Business\SupportController::class, 'index'])->name('support.index');
        Route::get('/support/create', [\App\Http\Controllers\Business\SupportController::class, 'create'])->name('support.create');
        Route::post('/support', [\App\Http\Controllers\Business\SupportController::class, 'store'])->name('support.store');
        Route::get('/support/{ticket}', [\App\Http\Controllers\Business\SupportController::class, 'show'])->name('support.show');
        Route::post('/support/{ticket}/reply', [\App\Http\Controllers\Business\SupportController::class, 'reply'])->name('support.reply');

        // Tickets
        Route::prefix('tickets')->name('tickets.')->group(function () {
            // Events
            Route::resource('events', \App\Http\Controllers\Business\EventController::class);
            Route::post('events/{event}/publish', [\App\Http\Controllers\Business\EventController::class, 'publish'])->name('events.publish');
            Route::post('events/{event}/cancel', [\App\Http\Controllers\Business\EventController::class, 'cancel'])->name('events.cancel');
            
            // Event Coupons
            Route::post('events/{event}/coupons', [\App\Http\Controllers\Business\EventCouponController::class, 'store'])->name('events.coupons.store');
            Route::delete('events/{event}/coupons/{coupon}', [\App\Http\Controllers\Business\EventCouponController::class, 'destroy'])->name('events.coupons.destroy');
            
            // Orders
            Route::get('orders', [\App\Http\Controllers\Business\TicketOrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [\App\Http\Controllers\Business\TicketOrderController::class, 'show'])->name('orders.show');
            
            // QR Scanner
            Route::get('scanner', [\App\Http\Controllers\Business\TicketScannerController::class, 'index'])->name('scanner');
            Route::post('scanner/verify', [\App\Http\Controllers\Business\TicketScannerController::class, 'verify'])->name('scanner.verify');
            Route::post('scanner/check-in', [\App\Http\Controllers\Business\TicketScannerController::class, 'checkIn'])->name('scanner.check-in');
            Route::post('scanner/manual-check-in', [\App\Http\Controllers\Business\TicketScannerController::class, 'manualCheckIn'])->name('scanner.manual-check-in');
        });
    });
});
