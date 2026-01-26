<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add HTTP cache headers for static/public pages
 * Optimizes response caching for fast server performance
 */
class CacheResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only cache GET requests for public pages
        if ($request->method() !== 'GET') {
            return $response;
        }

        // Don't cache authenticated pages
        if ($request->user()) {
            return $response;
        }

        // Don't cache API endpoints (they need fresh data)
        if ($request->is('api/*')) {
            return $response;
        }

        // Don't cache admin/dashboard pages
        if ($request->is('admin/*') || $request->is('business/*')) {
            return $response;
        }

        // Cache homepage and public pages for 1 hour (3600 seconds)
        // Browser will cache, reducing server load
        if ($request->is('/') || $request->is('page/*')) {
            $response->headers->set('Cache-Control', 'public, max-age=3600, s-maxage=3600');
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
            $response->headers->set('Vary', 'Accept-Encoding');
            return $response;
        }

        // Cache static content pages for 30 minutes
        if ($request->is('about') || $request->is('contact') || $request->is('privacy') || $request->is('terms')) {
            $response->headers->set('Cache-Control', 'public, max-age=1800, s-maxage=1800');
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 1800) . ' GMT');
            $response->headers->set('Vary', 'Accept-Encoding');
            return $response;
        }

        return $response;
    }
}
