<?php

namespace App\Services\Security;

use App\Support\AdminPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminHoneypotService
{
    public function isEnabled(): bool
    {
        return (bool) config('admin.honeypot.enabled', true);
    }

    public function isBanned(?string $ip): bool
    {
        if ($ip === null || $ip === '' || ! $this->isEnabled()) {
            return false;
        }

        return Cache::has($this->banKey($ip));
    }

    /**
     * Record a honeypot hit. Returns true when this request triggered (or refreshed) a ban.
     */
    public function recordHit(Request $request): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $ip = (string) ($request->ip() ?? '');
        if ($ip === '') {
            return false;
        }

        $path = '/'.ltrim($request->path(), '/');
        Log::channel('honeypot')->warning('admin_honeypot_hit', [
            'ip' => $ip,
            'method' => $request->method(),
            'path' => $path,
            'ua' => substr((string) $request->userAgent(), 0, 300),
            'referer' => substr((string) $request->headers->get('referer'), 0, 300),
        ]);

        $window = max(1, (int) config('admin.honeypot.hit_window_minutes', 60));
        $maxHits = max(1, (int) config('admin.honeypot.max_hits', 3));
        $banMinutes = max(1, (int) config('admin.honeypot.ban_minutes', 1440));

        $hitsKey = $this->hitsKey($ip);
        $hits = (int) Cache::get($hitsKey, 0) + 1;
        Cache::put($hitsKey, $hits, now()->addMinutes($window));

        if ($hits < $maxHits && ! $this->isBanned($ip)) {
            return false;
        }

        Cache::put($this->banKey($ip), [
            'banned_at' => now()->toIso8601String(),
            'hits' => $hits,
            'last_path' => $path,
        ], now()->addMinutes($banMinutes));

        Log::channel('honeypot')->alert('admin_honeypot_ban', [
            'ip' => $ip,
            'hits' => $hits,
            'ban_minutes' => $banMinutes,
            'path' => $path,
        ]);

        return true;
    }

    public function refuseResponse(): \Symfony\Component\HttpFoundation\Response
    {
        // Generic 404 — do not reveal that this is a trap or that /enter0 exists.
        return response(
            '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>',
            404,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    public function honeypotRoutePattern(): string
    {
        $prefix = preg_quote(AdminPath::honeypotPrefix(), '/');

        return '^'.$prefix.'(\/.*)?$';
    }

    private function hitsKey(string $ip): string
    {
        return 'security:honeypot:hits:'.$ip;
    }

    private function banKey(string $ip): string
    {
        return 'security:honeypot:ban:'.$ip;
    }
}
