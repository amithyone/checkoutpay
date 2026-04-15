<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyLiveSyncSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('services.live_sync.enabled', false)) {
            return response()->json([
                'success' => false,
                'message' => 'Live sync is disabled',
            ], 403);
        }

        $secret = (string) config('services.live_sync.secret', '');
        if ($secret === '') {
            return response()->json([
                'success' => false,
                'message' => 'Live sync is not configured',
            ], 500);
        }

        $allowedIps = (array) config('services.live_sync.allowed_ips', []);
        if (!empty($allowedIps) && !in_array((string) $request->ip(), $allowedIps, true)) {
            return response()->json([
                'success' => false,
                'message' => 'IP not allowed',
            ], 403);
        }

        $keyId = (string) $request->header('X-LiveSync-Key', '');
        $timestamp = (string) $request->header('X-LiveSync-Timestamp', '');
        $nonce = (string) $request->header('X-LiveSync-Nonce', '');
        $signature = (string) $request->header('X-LiveSync-Signature', '');

        if ($keyId === '' || $timestamp === '' || $nonce === '' || $signature === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing live sync auth headers',
            ], 401);
        }

        $expectedKeyId = (string) config('services.live_sync.key_id', '');
        if ($expectedKeyId !== '' && !hash_equals($expectedKeyId, $keyId)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid key id',
            ], 401);
        }

        if (!ctype_digit($timestamp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid timestamp',
            ], 401);
        }

        $maxDrift = (int) config('services.live_sync.max_drift_seconds', 300);
        if (abs(time() - (int) $timestamp) > $maxDrift) {
            return response()->json([
                'success' => false,
                'message' => 'Expired timestamp',
            ], 401);
        }

        $nonceTtl = (int) config('services.live_sync.nonce_ttl_seconds', 600);
        $nonceCacheKey = sprintf('live_sync:nonce:%s:%s', $keyId, $nonce);
        if (!Cache::add($nonceCacheKey, 1, now()->addSeconds($nonceTtl))) {
            return response()->json([
                'success' => false,
                'message' => 'Replay detected',
            ], 409);
        }

        $rawBody = (string) $request->getContent();
        $bodyHash = hash('sha256', $rawBody);
        $canonical = implode("\n", [
            strtoupper($request->method()),
            '/'.$request->path(),
            $timestamp,
            $nonce,
            $bodyHash,
        ]);

        $computed = hash_hmac('sha256', $canonical, $secret);
        if (!hash_equals($computed, $signature)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature',
            ], 401);
        }

        $request->attributes->set('live_sync_key_id', $keyId);
        $request->attributes->set('live_sync_nonce', $nonce);

        return $next($request);
    }
}
