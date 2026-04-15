<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BlockSqlInjectionPayload
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('security.sql_injection_guard.enabled', true)) {
            return $next($request);
        }

        $patterns = config('security.sql_injection_guard.patterns', []);

        foreach ($this->extractScalars($request->all()) as $value) {
            if (!is_string($value) || $value === '') {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (@preg_match($pattern, $value) === 1) {
                    Log::warning('Blocked request by SQL injection payload guard', [
                        'path' => $request->path(),
                        'ip' => $request->ip(),
                        'method' => $request->method(),
                        'ua' => $request->userAgent(),
                    ]);

                    abort(422, 'Request contains blocked SQL-like payload.');
                }
            }
        }

        return $next($request);
    }

    /**
     * @return array<int, mixed>
     */
    private function extractScalars(array $payload): array
    {
        $values = [];

        foreach ($payload as $item) {
            if (is_array($item)) {
                $values = array_merge($values, $this->extractScalars($item));
                continue;
            }

            $values[] = $item;
        }

        return $values;
    }
}
