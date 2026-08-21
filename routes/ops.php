<?php

use App\Http\Controllers\Ops\OpsMonitorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ops Sentinel routes (/ops/v1/*)
|--------------------------------------------------------------------------
|
| Auth: ops.monitor middleware (X-Ops-Key / Bearer OPS_MONITOR_KEY).
| These routes stay reachable during quarantine (EnsureNotQuarantined exempt).
|
*/

Route::get('/ping', [OpsMonitorController::class, 'ping']);
Route::get('/security', [OpsMonitorController::class, 'security']);
Route::get('/health', [OpsMonitorController::class, 'health']);
Route::get('/activity', [OpsMonitorController::class, 'activity']);
Route::get('/balances', [OpsMonitorController::class, 'balances']);
