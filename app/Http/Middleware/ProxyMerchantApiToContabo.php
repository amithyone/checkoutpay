<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Namecheap cutover helper: forward merchant API traffic to Contabo and return Contabo's response.
 * Keeps webhook-egress local so merchant IP allowlists still see check-outpay.com.
 */
class ProxyMerchantApiToContabo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldProxy($request)) {
            return $next($request);
        }

        $base = rtrim((string) config('checkout.api_proxy.to_base_url', 'https://check-outnow.com'), '/');
        $target = $base.$request->getRequestUri();

        try {
            $headers = [];
            foreach (['Authorization', 'X-API-Key', 'X-Api-Key', 'Accept', 'Content-Type', 'X-Request-Id'] as $h) {
                if ($request->headers->has($h)) {
                    $headers[$h] = $request->header($h);
                }
            }
            // Preserve common API key query/header variants
            if ($request->headers->has('x-api-key')) {
                $headers['X-API-Key'] = $request->header('x-api-key');
            }

            $pending = Http::timeout((int) config('checkout.api_proxy.timeout_seconds', 25))
                ->withHeaders($headers)
                ->withOptions(['allow_redirects' => false]);

            $method = strtolower($request->method());
            $body = $request->getContent();

            $response = match ($method) {
                'get' => $pending->get($target),
                'delete' => $pending->delete($target),
                'put' => $pending->withBody($body ?: '', $request->header('Content-Type') ?: 'application/json')->put($target),
                'patch' => $pending->withBody($body ?: '', $request->header('Content-Type') ?: 'application/json')->patch($target),
                default => $pending->withBody($body ?: '', $request->header('Content-Type') ?: 'application/json')->post($target),
            };

            $outHeaders = [];
            foreach (['Content-Type', 'Cache-Control', 'X-Request-Id'] as $h) {
                $val = $response->header($h);
                if (is_string($val) && $val !== '') {
                    $outHeaders[$h] = $val;
                }
            }
            $outHeaders['X-Checkout-Api-Proxy'] = 'contabo';

            return response($response->body(), $response->status(), $outHeaders);
        } catch (\Throwable $e) {
            Log::error('api_proxy_to_contabo_failed', [
                'target' => $target,
                'error' => $e->getMessage(),
            ]);

            if ((bool) config('checkout.api_proxy.fallback_local', false)) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Upstream checkout temporarily unavailable. Please retry.',
            ], 502);
        }
    }

    private function shouldProxy(Request $request): bool
    {
        if (! (bool) config('checkout.api_proxy.enabled', false)) {
            return false;
        }

        if (app()->environment('testing')) {
            return false;
        }

        $path = '/'.ltrim($request->path(), '/');
        if (! str_starts_with($path, '/api/')) {
            return false;
        }

        foreach ((array) config('checkout.api_proxy.skip_prefixes', []) as $prefix) {
            $prefix = '/'.ltrim((string) $prefix, '/');
            if ($prefix !== '/' && str_starts_with($path, rtrim($prefix, '/'))) {
                return false;
            }
        }

        return true;
    }
}
