<?php

namespace App\Http\Middleware;

use App\Services\Consumer\StepupFaceBridge;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyStepupFaceBridgeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $bridge = app(StepupFaceBridge::class);
        if (! $bridge->enabled()) {
            return response()->json(['success' => false, 'message' => 'Face bridge disabled.'], 403);
        }

        $timestamp = (string) $request->header('X-Face-Bridge-Timestamp', '');
        $nonce = (string) $request->header('X-Face-Bridge-Nonce', '');
        $signature = (string) $request->header('X-Face-Bridge-Signature', '');
        if ($timestamp === '' || $nonce === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return response()->json(['success' => false, 'message' => 'Missing face bridge auth headers.'], 401);
        }
        if (abs(time() - (int) $timestamp) > 300) {
            return response()->json(['success' => false, 'message' => 'Expired face bridge timestamp.'], 401);
        }

        $nonceKey = 'face_bridge_nonce:'.$nonce;
        if (! Cache::add($nonceKey, 1, now()->addMinutes(10))) {
            return response()->json(['success' => false, 'message' => 'Replay detected.'], 409);
        }

        $raw = $request->getContent();
        $expected = $bridge->signRequest(
            $timestamp,
            $nonce,
            $raw,
            (string) config('checkface.stepup_bridge_secret', '')
        );
        if (! hash_equals($expected, $signature)) {
            return response()->json(['success' => false, 'message' => 'Invalid face bridge signature.'], 401);
        }

        return $next($request);
    }
}
