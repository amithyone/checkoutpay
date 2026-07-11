<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireWalletOpsAccess
{
    /**
     * Allow admin, super_admin, and wallet_support into wallet ops routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();

        if (! $admin instanceof Admin || ! $admin->canAccessWalletOps()) {
            abort(403, 'This action requires wallet operations access.');
        }

        return $next($request);
    }
}
