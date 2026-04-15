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
                    if ($this->isLikelyBenignMatch($pattern, $value)) {
                        continue;
                    }

                    Log::warning('Blocked request by SQL injection payload guard', [
                        'path' => $request->path(),
                        'ip' => $request->ip(),
                        'method' => $request->method(),
                        'ua' => $request->userAgent(),
                        'pattern' => $pattern,
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

    private function isLikelyBenignMatch(string $pattern, string $value): bool
    {
        // Only treat SQL-style comment markers as suspicious when the same value
        // also contains query-like SQL verbs/tokens.
        if ($pattern !== '/(?:--\s+|#\s+|\/\*)/i') {
            return false;
        }

        return @preg_match('/\b(select|insert|update|delete|union|drop|alter|truncate|from|where)\b/i', $value) !== 1;
    }
}
