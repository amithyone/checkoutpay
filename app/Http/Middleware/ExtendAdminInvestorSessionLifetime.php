<?php

namespace App\Http\Middleware;

use App\Support\AdminPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep admin panel and investor pitch sessions alive longer so open forms
 * do not hit Laravel's "419 Page Expired" as quickly as the global default.
 */
class ExtendAdminInvestorSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            AdminPath::requestIsAdminPanel($request)
            || $request->is('investor', 'investor/*')
            || $request->is('session/keepalive')
        ) {
            $minutes = max(
                (int) config('session.lifetime', 120),
                (int) config('session.admin_investor_lifetime', 720)
            );
            config(['session.lifetime' => $minutes]);
        }

        return $next($request);
    }
}
