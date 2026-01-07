<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required',
            ], 401);
        }

        $business = Business::where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive API key',
            ], 401);
        }

        // Attach business to request
        $request->merge(['business' => $business]);
        $request->setUserResolver(function () use ($business) {
            return $business;
        });

        return $next($request);
    }
}
