<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict admin panel routes based on role defaults and per-staff page permissions.
 */
class RestrictWalletSupportAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();

        if (! $admin instanceof Admin) {
            return $next($request);
        }

        if ($admin->isSuperAdmin() || $admin->role === Admin::ROLE_ADMIN || $admin->isTaxAdmin()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName !== null && ! $admin->canAccessRoute($routeName)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
