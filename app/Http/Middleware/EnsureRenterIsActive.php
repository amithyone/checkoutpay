<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRenterIsActive
{
    /**
     * Block rentals API actions for disabled renter accounts.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'getAttribute') && ! (bool) $user->getAttribute('is_active')) {
            return response()->json([
                'message' => 'Your renter account is disabled. Please contact support.',
            ], 403);
        }

        return $next($request);
    }
}
