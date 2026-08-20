<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block /setup/* once APP_SETUP_COMPLETE=true (production installs).
 */
class EnsureSetupNotComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('checkout.setup_complete', false)) {
            abort(404);
        }

        return $next($request);
    }
}
