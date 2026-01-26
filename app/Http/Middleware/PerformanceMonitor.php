<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitor
{
    /**
     * Handle an incoming request.
     * Logs slow requests and performance metrics
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip logging for certain paths (health checks, static assets, etc.)
        if ($this->shouldSkipLogging($request)) {
            return $next($request);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $queryCount = 0;
        $slowQueries = [];

        // Enable query logging
        DB::enableQueryLog();

        try {
            $response = $next($request);
        } finally {
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB

            // Get query log
            $queries = DB::getQueryLog();
            $queryCount = count($queries);
            $totalQueryTime = 0;

            // Find slow queries (> 100ms)
            foreach ($queries as $query) {
                $queryTime = $query['time'] ?? 0;
                $totalQueryTime += $queryTime;
                
                if ($queryTime > 100) {
                    $slowQueries[] = [
                        'sql' => $query['query'],
                        'bindings' => $query['bindings'],
                        'time' => $queryTime . 'ms',
                    ];
                }
            }

            // Log slow requests (> 500ms) or requests with slow queries
            $isSlow = $duration > 500 || !empty($slowQueries) || $queryCount > 50;

            if ($isSlow) {
                $logData = [
                    'type' => 'slow_request',
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'route' => $request->route()?->getName(),
                    'duration_ms' => round($duration, 2),
                    'memory_mb' => round($memoryUsed, 2),
                    'query_count' => $queryCount,
                    'total_query_time_ms' => round($totalQueryTime, 2),
                    'avg_query_time_ms' => $queryCount > 0 ? round($totalQueryTime / $queryCount, 2) : 0,
                    'slow_queries' => $slowQueries,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now()->toDateTimeString(),
                ];

                // Log with appropriate level
                if ($duration > 2000) {
                    Log::error('Very slow request detected', $logData);
                } elseif ($duration > 1000) {
                    Log::warning('Slow request detected', $logData);
                } else {
                    Log::info('Request performance metrics', $logData);
                }
            }

            // Always log account assignment endpoints for monitoring
            if ($this->isAccountAssignmentEndpoint($request)) {
                Log::info('Account assignment endpoint performance', [
                    'type' => 'account_assignment',
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'duration_ms' => round($duration, 2),
                    'query_count' => $queryCount,
                    'total_query_time_ms' => round($totalQueryTime, 2),
                    'memory_mb' => round($memoryUsed, 2),
                    'slow_queries' => $slowQueries,
                ]);
            }
        }

        return $response;
    }

    /**
     * Check if logging should be skipped for this request
     */
    protected function shouldSkipLogging(Request $request): bool
    {
        $path = $request->path();

        // Skip static assets and health checks
        $skipPaths = [
            'css',
            'js',
            'images',
            'fonts',
            'favicon.ico',
            'health',
            'ping',
        ];

        foreach ($skipPaths as $skipPath) {
            if (str_contains($path, $skipPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this is an account assignment endpoint
     */
    protected function isAccountAssignmentEndpoint(Request $request): bool
    {
        $path = $request->path();
        
        return str_contains($path, 'payment-request') 
            || str_contains($path, 'checkout')
            || str_contains($path, 'api/v1/payment');
    }
}
