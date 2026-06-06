<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce a single canonical URL shape for public GET/HEAD HTML pages:
 * preferred host (APP_URL), no trailing slash (except /), no index.php in path.
 */
final class NormalizeCanonicalUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $path = $request->getPathInfo();
        if ($this->shouldSkip($path)) {
            return $next($request);
        }

        $preferredBase = rtrim((string) config('app.url'), '/');
        if ($preferredBase === '') {
            return $next($request);
        }

        $preferredParts = parse_url($preferredBase);
        $preferredHost = strtolower((string) ($preferredParts['host'] ?? ''));
        $preferredScheme = (string) ($preferredParts['scheme'] ?? 'https');
        $currentHost = strtolower($request->getHost());

        $normalizedPath = $this->normalizePath($path);
        $needsPathFix = $normalizedPath !== $path;
        $needsHostFix = ! app()->environment('testing')
            && $preferredHost !== ''
            && $currentHost !== $preferredHost;
        $needsSchemeFix = ! app()->environment('testing')
            && $request->getScheme() !== $preferredScheme
            && app()->environment('production');

        if ($needsPathFix || $needsHostFix || $needsSchemeFix) {
            $target = $preferredScheme.'://'.$preferredHost.$normalizedPath;
            $query = $request->getQueryString();
            if (is_string($query) && $query !== '') {
                $target .= '?'.$query;
            }

            return redirect()->away($target, 301);
        }

        return $next($request);
    }

    private function shouldSkip(string $path): bool
    {
        if (str_starts_with($path, '/api')
            || str_starts_with($path, '/admin')
            || str_starts_with($path, '/business')
            || str_starts_with($path, '/pay')
            || str_starts_with($path, '/wallet/')
            || str_starts_with($path, '/my-account')
            || str_starts_with($path, '/cron/')
            || str_starts_with($path, '/storage/')
        ) {
            return true;
        }

        return preg_match('/\.(xml|txt|json|css|js|png|jpe?g|gif|webp|svg|ico|woff2?|map)$/i', $path) === 1;
    }

    private function normalizePath(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        $path = '/'.trim($path, '/');
        if (str_ends_with($path, '/index.php')) {
            $path = substr($path, 0, -strlen('/index.php')) ?: '/';
        }

        return rtrim($path, '/') ?: '/';
    }
}
