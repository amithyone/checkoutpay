<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protect sensitive public HTTP triggers (cron, transaction check, stats).
 * Requires CRON_EMAIL_FETCH_TOKEN (header X-Cron-Token or ?token=).
 */
class VerifyCronToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $required = (string) config('checkout.cron_api_token', '');

        if ($required === '') {
            if (app()->environment('local', 'testing')) {
                return $next($request);
            }

            Log::warning('cron_api_blocked_token_not_configured', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Endpoint locked: set CRON_EMAIL_FETCH_TOKEN in .env and pass it as X-Cron-Token (or ?token=).',
            ], 503);
        }

        $provided = (string) (
            $request->header('X-Cron-Token')
            ?? $request->header('X-Checkout-Cron-Token')
            ?? $request->query('token')
            ?? ''
        );

        if ($provided === '' || ! hash_equals($required, $provided)) {
            Log::warning('cron_api_unauthorized', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing cron token',
            ], 401);
        }

        return $next($request);
    }
}
