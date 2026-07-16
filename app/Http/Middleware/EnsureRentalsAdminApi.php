<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRentalsAdminApi
{
    /**
     * Allow checkout admins (not tax-only) for rentals admin API routes (Sanctum token).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Admin) {
            abort(403, 'Forbidden.');
        }

        if (! $user->is_active) {
            abort(403, 'Account disabled.');
        }

        if ($user->isTaxAdmin()) {
            abort(403, 'Not authorized for rentals admin.');
        }

        return $next($request);
    }
}
