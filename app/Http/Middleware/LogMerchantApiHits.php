<?php

namespace App\Http\Middleware;

use App\Models\ApiHitLog;
use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogMerchantApiHits
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('api_hit_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('OPTIONS')) {
            return;
        }

        try {
            $status = $response->getStatusCode();
            $started = (float) $request->attributes->get('api_hit_started_at', microtime(true));
            $origin = $this->truncate((string) $request->headers->get('Origin', ''), 500);
            $referer = $this->truncate((string) $request->headers->get('Referer', ''), 500);
            $host = $this->hostFrom($origin) ?: $this->hostFrom($referer);

            $business = $request->user();
            $businessId = $business instanceof Business ? $business->id : null;

            $rawKey = (string) ($request->header('X-API-Key') ?? $request->input('api_key') ?? '');
            $keyHint = $rawKey !== '' ? substr($rawKey, 0, 8) : null;

            ApiHitLog::query()->create([
                'method' => strtoupper($request->method()),
                'path' => $this->truncate('/'.$request->path(), 500),
                'origin' => $origin !== '' ? $origin : null,
                'referer' => $referer !== '' ? $referer : null,
                'website_host' => $host,
                'ip' => $request->ip(),
                'user_agent' => $this->truncate((string) $request->userAgent(), 500),
                'business_id' => $businessId,
                'api_key_hint' => $keyHint,
                'status_code' => $status,
                'successful' => $status >= 200 && $status < 400,
                'message' => $this->responseMessage($response),
                'duration_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Never break the API because logging failed.
        }
    }

    private function hostFrom(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private function responseMessage(Response $response): ?string
    {
        $body = $response->getContent();
        if (! is_string($body) || $body === '') {
            return null;
        }

        $json = json_decode($body, true);
        if (is_array($json) && isset($json['message']) && is_string($json['message'])) {
            return $this->truncate($json['message'], 500);
        }

        return null;
    }

    private function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max - 1).'…';
    }
}
