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
        $required = trim((string) config('checkout.ops_monitor.key', ''));

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
        $headerFlags = $this->headerFlags($request);

        if ($provided === '' || ! hash_equals($required, $provided)) {
            Log::warning('ops_monitor_unauthorized', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'provided_len' => strlen($provided),
                'required_len' => strlen($required),
                'headers' => $headerFlags,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized: Invalid or missing ops monitor key',
                'hint' => 'Send header X-Ops-Key: <OPS_MONITOR_KEY> (or Authorization: Bearer <key>). Do not put the key only in JSON body.',
                'received_key_len' => strlen($provided),
                'expected_key_len' => strlen($required),
                'saw_x_ops_key' => $headerFlags['x_ops_key'],
                'saw_authorization' => $headerFlags['authorization'],
            ], 401);
        }

        return $next($request);
    }

    /**
     * @return array{x_ops_key: bool, authorization: bool, x_ops_monitor_key: bool, x_api_key: bool}
     */
    private function headerFlags(Request $request): array
    {
        return [
            'x_ops_key' => $this->headerNonEmpty($request, 'X-Ops-Key'),
            'authorization' => $this->headerNonEmpty($request, 'Authorization'),
            'x_ops_monitor_key' => $this->headerNonEmpty($request, 'X-Ops-Monitor-Key'),
            'x_api_key' => $this->headerNonEmpty($request, 'X-Api-Key'),
        ];
    }

    private function headerNonEmpty(Request $request, string $name): bool
    {
        return trim((string) $request->header($name, '')) !== '';
    }

    private function extractKey(Request $request): string
    {
        foreach (['X-Ops-Key', 'X-Ops-Monitor-Key', 'Ops-Key', 'X-Api-Key'] as $header) {
            $value = $this->normalizeKey((string) $request->header($header, ''));
            if ($value !== '') {
                return $value;
            }
        }

        $auth = (string) $request->header('Authorization', '');
        if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $auth, $m)) {
            return $this->normalizeKey($m[1]);
        }

        // Last-resort query for PowerShell/curl debugging only.
        $query = $this->normalizeKey((string) $request->query('ops_key', ''));
        if ($query !== '') {
            return $query;
        }

        return '';
    }

    private function normalizeKey(string $value): string
    {
        $value = trim($value);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
            $value = trim($value);
        }

        return $value;
    }
}
