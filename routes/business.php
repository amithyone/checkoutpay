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
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')->name('business.')->group(function () {
    // Business authentication routes
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Protected business routes
    Route::middleware('auth:business')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Transactions
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');

        // Withdrawals
        Route::get('/withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::get('/withdrawals/create', [WithdrawalController::class, 'create'])->name('withdrawals.create');
        Route::post('/withdrawals', [WithdrawalController::class, 'store'])->name('withdrawals.store');
        Route::get('/withdrawals/{withdrawal}', [WithdrawalController::class, 'show'])->name('withdrawals.show');

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

        // API Keys & Integration
        Route::get('/keys', [KeysController::class, 'index'])->name('keys.index');
        Route::post('/keys/request-account-number', [KeysController::class, 'requestAccountNumber'])->name('keys.request-account-number');
    });
});
