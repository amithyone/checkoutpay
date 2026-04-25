<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared secret between Tagine API and Checkout (header X-Tagine-Otp-Secret).
 */
class VerifyTagineOtpSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('tagine.secret');
        if (! is_string($expected) || $expected === '') {
            return response()->json([
                'message' => 'Tagine bridge is not configured on this server (TAGINE_OTP_SECRET).',
            ], 503);
        }

        $provided = $request->header('X-Tagine-Otp-Secret');
        if (! is_string($provided) || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
