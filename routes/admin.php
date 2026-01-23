<?php

use App\Http\Controllers\Admin\AccountNumberController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmailAccountController;
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
});
