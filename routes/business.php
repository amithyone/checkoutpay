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
        Route::get('/verification/{verification}/download', [\App\Http\Controllers\Business\VerificationController::class, 'download'])->name('verification.download');

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

        // Events Management
        Route::get('/events', [\App\Http\Controllers\Business\EventController::class, 'index'])->name('events.index');
        Route::get('/events/create', [\App\Http\Controllers\Business\EventController::class, 'create'])->name('events.create');
        Route::post('/events', [\App\Http\Controllers\Business\EventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}', [\App\Http\Controllers\Business\EventController::class, 'show'])->name('events.show');
        Route::get('/events/{event}/edit', [\App\Http\Controllers\Business\EventController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [\App\Http\Controllers\Business\EventController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [\App\Http\Controllers\Business\EventController::class, 'destroy'])->name('events.destroy');
        Route::post('/events/{event}/publish', [\App\Http\Controllers\Business\EventController::class, 'publish'])->name('events.publish');
        Route::post('/events/{event}/cancel', [\App\Http\Controllers\Business\EventController::class, 'cancel'])->name('events.cancel');

        // Ticket Types
        Route::post('/events/{event}/ticket-types', [\App\Http\Controllers\Business\TicketTypeController::class, 'store'])->name('ticket-types.store');
        Route::put('/ticket-types/{ticketType}', [\App\Http\Controllers\Business\TicketTypeController::class, 'update'])->name('ticket-types.update');
        Route::delete('/ticket-types/{ticketType}', [\App\Http\Controllers\Business\TicketTypeController::class, 'destroy'])->name('ticket-types.destroy');

        // Ticket Orders
        Route::get('/events/{event}/orders', [\App\Http\Controllers\Business\TicketOrderController::class, 'index'])->name('events.orders');
        Route::get('/orders/{order}', [\App\Http\Controllers\Business\TicketOrderController::class, 'show'])->name('orders.show');

        // Check-in
        Route::get('/events/{event}/check-in', [\App\Http\Controllers\Business\CheckInController::class, 'index'])->name('events.check-in');
        Route::post('/events/{event}/check-in', [\App\Http\Controllers\Business\CheckInController::class, 'checkIn'])->name('events.check-in.post');
        Route::get('/events/{event}/check-in/statistics', [\App\Http\Controllers\Business\CheckInController::class, 'statistics'])->name('events.check-in.statistics');
    });
});
