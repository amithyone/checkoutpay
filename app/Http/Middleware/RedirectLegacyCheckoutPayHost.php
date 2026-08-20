<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permanent redirect from legacy check-outpay.com → check-outnow.com (path + query preserved).
 * GET/HEAD use 301; other methods use 308 so API POSTs keep their method/body.
 */
class RedirectLegacyCheckoutPayHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldRedirect($request)) {
            return $next($request);
        }

        $targetBase = rtrim((string) config('checkout.legacy_host_redirect_to', 'https://check-outnow.com'), '/');
        $path = $request->getRequestUri(); // includes query string
        if ($path === '' || ! str_starts_with($path, '/')) {
            $path = '/';
        }

        $target = $targetBase.$path;
        $status = in_array($request->method(), ['GET', 'HEAD'], true) ? 301 : 308;

        return redirect()->away($target, $status);
    }

    private function shouldRedirect(Request $request): bool
    {
        if (! (bool) config('checkout.legacy_host_redirect_enabled', true)) {
            return false;
        }

        if (app()->environment('testing', 'local')) {
            // Allow tests to opt in via config; default off in testing
            if (app()->environment('testing') && ! config('checkout.legacy_host_redirect_force_in_tests', false)) {
                return false;
            }
        }

        $host = strtolower($request->getHost());
        $legacy = config('checkout.legacy_hosts', ['check-outpay.com', 'www.check-outpay.com']);

        return in_array($host, $legacy, true);
    }
}
