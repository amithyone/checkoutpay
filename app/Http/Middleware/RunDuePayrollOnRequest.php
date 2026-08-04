<?php

namespace App\Http\Middleware;

use App\Services\Business\BusinessPayrollDueRunner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hang due payroll processing on normal site traffic so daily/weekly salary
 * items still run when cron/scheduler is unavailable.
 */
class RunDuePayrollOnRequest
{
    public function __construct(
        private BusinessPayrollDueRunner $runner,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Skip noisy/static-ish paths; still covers dashboard + API traffic.
        $path = ltrim($request->path(), '/');
        if ($path === '' || str_starts_with($path, 'css/') || str_starts_with($path, 'js/')
            || str_starts_with($path, 'storage/') || str_starts_with($path, 'build/')) {
            return;
        }

        // After the response is sent — at most about once a minute site-wide.
        $this->runner->tick(force: false, minIntervalSeconds: 60);
    }
}
