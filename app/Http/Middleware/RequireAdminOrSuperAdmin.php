<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminOrSuperAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();

        if (!$admin || !in_array($admin->role, [\App\Models\Admin::ROLE_SUPER_ADMIN, \App\Models\Admin::ROLE_ADMIN])) {
            abort(403, 'This action requires admin or super admin privileges.');
        }

        return $next($request);
    }
}
