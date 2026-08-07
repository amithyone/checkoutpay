<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionKeepAliveController extends Controller
{
    /**
     * Touch the session and return a fresh CSRF token for open admin/investor pages.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Touch last activity without rotating the session id (avoids races on open tabs).
        $request->session()->put('_keepalive_at', now()->getTimestamp());

        return response()->json([
            'ok' => true,
            'csrf_token' => csrf_token(),
            'lifetime_minutes' => (int) config('session.lifetime'),
        ]);
    }
}
