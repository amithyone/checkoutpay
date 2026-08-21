<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protect /ops/v1/* for Checkout Ops Sentinel.
 * Requires OPS_MONITOR_KEY via X-Ops-Key or Authorization: Bearer.
 */
class VerifyOpsMonitorKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $required = (string) config('checkout.ops_monitor.key', '');

        if ($required === '') {
            Log::warning('ops_monitor_blocked_key_not_configured', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Ops monitor locked: set OPS_MONITOR_KEY and pass X-Ops-Key.',
            ], 503);
        }

        $allowedIps = config('checkout.ops_monitor.allowed_ips', []);
        if (is_array($allowedIps) && $allowedIps !== []) {
            $ip = (string) ($request->ip() ?? '');
            if ($ip === '' || ! in_array($ip, $allowedIps, true)) {
                Log::warning('ops_monitor_ip_denied', [
                    'path' => $request->path(),
                    'ip' => $ip,
                ]);

                return response()->json([
                    'ok' => false,
                    'message' => 'Forbidden',
                ], 403);
            }
        }

        $provided = $this->extractKey($request);

        if ($provided === '' || ! hash_equals($required, $provided)) {
            Log::warning('ops_monitor_unauthorized', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized: Invalid or missing ops monitor key',
            ], 401);
        }

        return $next($request);
    }

    private function extractKey(Request $request): string
    {
        $header = (string) ($request->header('X-Ops-Key') ?? '');
        if ($header !== '') {
            return $header;
        }

        $auth = (string) ($request->header('Authorization') ?? '');
        if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $auth, $m)) {
            return trim($m[1]);
        }

        return '';
    }
}
