<?php

use App\Http\Middleware\AdminHoneypotTrap;
use App\Support\AdminPath;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin honeypot (decoy path, default /admin)
|--------------------------------------------------------------------------
|
| Real admin panel is under config('admin.path') — default /enter0.
|
*/

$honeypot = AdminPath::honeypotPrefix();
$real = AdminPath::prefix();

if ($honeypot !== '' && strcasecmp($honeypot, $real) !== 0) {
    Route::middleware(AdminHoneypotTrap::class)
        ->prefix($honeypot)
        ->group(function () {
            Route::any('/{any?}', fn () => abort(404))->where('any', '.*');
        });
}
