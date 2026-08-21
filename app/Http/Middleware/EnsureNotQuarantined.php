<?php

namespace App\Http\Middleware;

use App\Services\Quarantine\QuarantineService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotQuarantined
{
    public function __construct(private QuarantineService $quarantine) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExempt($request)) {
            return $next($request);
        }

        if ($this->quarantine->guard()) {
            return $this->quarantineResponse($request);
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return $path === 'quarantine/status'
            || $path === 'quarantine/unlock'
            || str_starts_with($path, 'quarantine/')
            || str_starts_with($path, 'ops/v1');
    }


    private function quarantineResponse(Request $request): Response
    {
        $status = $this->quarantine->status();
        $payload = [
            'status' => 'quarantine',
            'message' => 'This site is in quarantine. Do not run migrate. Fix DB_HOST in .error, then unlock.',
            'reasons' => $status['reasons'],
            'status_url' => url('/quarantine/status'),
        ];

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($payload, 503);
        }

        return response()->view('quarantine.locked', [
            'reasons' => $status['reasons'],
            'statusUrl' => url('/quarantine/status'),
            'unlockUrl' => url('/quarantine/unlock'),
        ], 503);
    }
}
