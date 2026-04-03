<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTaxAdminApi
{
    /**
     * Allow only tax and super_admin roles for tax admin API routes (Sanctum token).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        if (!in_array($user->role, [Admin::ROLE_TAX, Admin::ROLE_SUPER_ADMIN], true)) {
            abort(403, 'Not authorized for tax admin.');
        }

        if (!$user->is_active) {
            abort(403, 'Account disabled.');
        }

        return $next($request);
    }
}
