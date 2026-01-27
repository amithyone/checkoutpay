<?php

use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group.
|
*/

// Public Event Routes
Route::prefix('events')->name('public.events.')->group(function () {
    Route::get('/', [EventController::class, 'index'])->name('index');
    Route::get('/{event}', [EventController::class, 'show'])->name('show');
});

// Public Ticket Routes
Route::prefix('tickets')->name('public.tickets.')->group(function () {
    Route::post('/orders', [TicketController::class, 'createOrder'])->name('orders.create');
    Route::get('/orders/{order}/payment', [TicketController::class, 'payment'])->name('payment');
    Route::get('/orders/{order}', [TicketController::class, 'show'])->name('show');
    Route::get('/my-tickets', [TicketController::class, 'myTickets'])->name('my-tickets');
    Route::post('/verify', [TicketController::class, 'verify'])->name('verify');
});
