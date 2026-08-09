<?php

namespace App\Http\Controllers;

use App\Support\MevonPayEgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Authenticated reverse proxy: live shared hosting → Contabo → MevonPay.
 * Enable only on the Mevon-reachable host (Contabo).
 */
class MevonEgressProxyController extends Controller
{
    public function __invoke(Request $request, string $path = ''): SymfonyResponse
    {
        if (! MevonPayEgress::proxyEnabled()) {
            return response('Mevon egress proxy disabled.', 404);
        }

        $token = MevonPayEgress::proxyToken();
        if ($token === '') {
            Log::error('mevonpay.egress_proxy_misconfigured', ['reason' => 'empty_token']);

            return response('Proxy misconfigured.', 503);
        }

        $provided = (string) $request->header(MevonPayEgress::TOKEN_HEADER, '');
        if (! hash_equals($token, $provided)) {
            Log::warning('mevonpay.egress_proxy_denied', [
                'reason' => 'bad_token',
                'ip' => $request->ip(),
            ]);

            return response('Unauthorized.', 401);
        }

        $allowed = MevonPayEgress::proxyAllowedIps();
        if ($allowed !== [] && ! in_array($request->ip(), $allowed, true)) {
            Log::warning('mevonpay.egress_proxy_denied', [
                'reason' => 'ip_not_allowed',
                'ip' => $request->ip(),
            ]);

            return response('Forbidden.', 403);
        }

        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..')) {
            return response('Invalid path.', 400);
        }

        if (! str_starts_with($path, 'V1/') && ! str_starts_with($path, 'v1/')) {
            return response('Path not allowed.', 400);
        }

        $upstream = MevonPayEgress::upstreamBase().'/'.$path;
        $timeout = (int) config('services.mevonpay.timeout_seconds', 20);
        $connect = (int) config('services.mevonpay.connect_timeout_seconds', 3);

        $forwardHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        $auth = $request->header('Authorization');
        if (is_string($auth) && $auth !== '') {
            $forwardHeaders['Authorization'] = $auth;
        }

        $method = strtoupper($request->method());
        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return response('Method not allowed.', 405);
        }

        try {
            $client = Http::timeout($timeout)
                ->connectTimeout($connect)
                ->withHeaders($forwardHeaders);

            $response = match ($method) {
                'GET' => $client->get($upstream),
                'DELETE' => $client->withBody($request->getContent() ?: '', 'application/json')->delete($upstream),
                default => $client->withBody($request->getContent() ?: '{}', 'application/json')->send($method, $upstream),
            };
        } catch (\Throwable $e) {
            Log::warning('mevonpay.egress_proxy_upstream_failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'failed',
                'msg' => 'Upstream MevonPay unreachable from egress proxy.',
            ], 504);
        }

        Log::info('mevonpay.egress_proxy_ok', [
            'path' => $path,
            'ip' => $request->ip(),
            'upstream_status' => $response->status(),
        ]);

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type') ?: 'application/json');
    }
}
