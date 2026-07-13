<?php

namespace App\Services\Security;

use App\Support\AdminPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminHoneypotService
{
    private const INDEX_KEY = 'security:honeypot:ban_index';

    public function isEnabled(): bool
    {
        return (bool) config('admin.honeypot.enabled', true);
    }

    public function isBanned(?string $ip): bool
    {
        $ip = $this->normalizeIp($ip);
        if ($ip === null) {
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

        $ip = $this->normalizeIp($request->ip());
        if ($ip === null) {
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

        $this->banIp($ip, [
            'hits' => $hits,
            'last_path' => $path,
            'source' => 'honeypot',
            'ua' => substr((string) $request->userAgent(), 0, 300),
        ], $banMinutes);

        Log::channel('honeypot')->alert('admin_honeypot_ban', [
            'ip' => $ip,
            'hits' => $hits,
            'ban_minutes' => $banMinutes,
            'path' => $path,
        ]);

        return true;
    }

    /**
     * Manually ban an IP (admin action).
     *
     * @param  array{hits?: int, last_path?: string|null, source?: string, note?: string|null, banned_by?: int|null, ua?: string|null}  $meta
     */
    public function banIp(string $ip, array $meta = [], ?int $minutes = null): bool
    {
        $ip = $this->normalizeIp($ip);
        if ($ip === null) {
            return false;
        }

        $banMinutes = max(1, $minutes ?? (int) config('admin.honeypot.ban_minutes', 1440));
        $existing = Cache::get($this->banKey($ip), []);
        if (! is_array($existing)) {
            $existing = [];
        }

        $payload = [
            'ip' => $ip,
            'banned_at' => $existing['banned_at'] ?? now()->toIso8601String(),
            'expires_at' => now()->addMinutes($banMinutes)->toIso8601String(),
            'ban_minutes' => $banMinutes,
            'hits' => (int) ($meta['hits'] ?? $existing['hits'] ?? 0),
            'last_path' => $meta['last_path'] ?? $existing['last_path'] ?? null,
            'source' => $meta['source'] ?? $existing['source'] ?? 'manual',
            'note' => $meta['note'] ?? $existing['note'] ?? null,
            'banned_by' => $meta['banned_by'] ?? $existing['banned_by'] ?? null,
            'ua' => $meta['ua'] ?? $existing['ua'] ?? null,
        ];

        Cache::put($this->banKey($ip), $payload, now()->addMinutes($banMinutes));
        $this->addToIndex($ip);

        Log::channel('honeypot')->alert('admin_honeypot_manual_ban', [
            'ip' => $ip,
            'source' => $payload['source'],
            'ban_minutes' => $banMinutes,
            'banned_by' => $payload['banned_by'],
            'note' => $payload['note'],
        ]);

        return true;
    }

    public function unbanIp(string $ip): bool
    {
        $ip = $this->normalizeIp($ip);
        if ($ip === null) {
            return false;
        }

        $had = Cache::has($this->banKey($ip));
        Cache::forget($this->banKey($ip));
        Cache::forget($this->hitsKey($ip));
        $this->removeFromIndex($ip);

        if ($had) {
            Log::channel('honeypot')->info('admin_honeypot_unban', ['ip' => $ip]);
        }

        return $had;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBans(): array
    {
        $this->reconcileIndexFromCacheStore();

        $ips = $this->indexIps();
        $rows = [];

        foreach ($ips as $ip) {
            $meta = Cache::get($this->banKey($ip));
            if (! is_array($meta)) {
                $this->removeFromIndex($ip);

                continue;
            }

            $rows[] = array_merge(['ip' => $ip], $meta);
        }

        usort($rows, function (array $a, array $b): int {
            return strcmp((string) ($b['banned_at'] ?? ''), (string) ($a['banned_at'] ?? ''));
        });

        return $rows;
    }

    /**
     * Pick up bans that were stored before the ban index existed (database cache).
     */
    private function reconcileIndexFromCacheStore(): void
    {
        try {
            if (config('cache.default') !== 'database') {
                return;
            }

            $prefix = (string) config('cache.prefix', '');
            $needle = $prefix.'security:honeypot:ban:';
            $keys = \Illuminate\Support\Facades\DB::table('cache')
                ->where('key', 'like', $needle.'%')
                ->pluck('key');

            $found = [];
            foreach ($keys as $key) {
                $ip = substr((string) $key, strlen($needle));
                if ($this->normalizeIp($ip) !== null) {
                    $found[] = $ip;
                }
            }

            if ($found === []) {
                return;
            }

            $merged = array_values(array_unique(array_merge($this->indexIps(), $found)));
            Cache::forever(self::INDEX_KEY, $merged);
        } catch (\Throwable) {
            // ignore — index still works for new bans
        }
    }

    public function banCount(): int
    {
        return count($this->listBans());
    }

    /**
     * Recent honeypot log lines (hits / bans).
     *
     * @return list<array{time: string, level: string, event: string, ip: string|null, path: string|null, message: string}>
     */
    public function recentLogEntries(int $limit = 80): array
    {
        $path = storage_path('logs/honeypot-'.now()->format('Y-m-d').'.log');
        if (! is_file($path)) {
            // Fall back to newest honeypot-*.log
            $files = glob(storage_path('logs/honeypot-*.log')) ?: [];
            rsort($files);
            $path = $files[0] ?? null;
        }

        if ($path === null || ! is_readable($path)) {
            return [];
        }

        $lines = $this->tailFile($path, max(50, $limit * 3));
        $entries = [];

        foreach (array_reverse($lines) as $line) {
            if (! preg_match('/^\[(?P<time>[^\]]+)\]\s+\w+\.(?P<level>\w+):\s+(?P<event>\S+)\s*(?P<json>\{.*\})?\s*$/', $line, $m)) {
                continue;
            }

            $json = [];
            if (! empty($m['json'])) {
                $decoded = json_decode($m['json'], true);
                if (is_array($decoded)) {
                    $json = $decoded;
                }
            }

            $entries[] = [
                'time' => $m['time'],
                'level' => strtolower($m['level']),
                'event' => $m['event'],
                'ip' => isset($json['ip']) ? (string) $json['ip'] : null,
                'path' => isset($json['path']) ? (string) $json['path'] : null,
                'message' => $line,
            ];

            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
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

    public function normalizeIp(?string $ip): ?string
    {
        $ip = trim((string) $ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $ip;
    }

    /**
     * @return list<string>
     */
    private function indexIps(): array
    {
        $ips = Cache::get(self::INDEX_KEY, []);
        if (! is_array($ips)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('strval', $ips))));
    }

    private function addToIndex(string $ip): void
    {
        $ips = $this->indexIps();
        if (! in_array($ip, $ips, true)) {
            $ips[] = $ip;
        }
        Cache::forever(self::INDEX_KEY, $ips);
    }

    private function removeFromIndex(string $ip): void
    {
        $ips = array_values(array_filter($this->indexIps(), fn (string $x) => $x !== $ip));
        Cache::forever(self::INDEX_KEY, $ips);
    }

    private function hitsKey(string $ip): string
    {
        return 'security:honeypot:hits:'.$ip;
    }

    private function banKey(string $ip): string
    {
        return 'security:honeypot:ban:'.$ip;
    }

    /**
     * @return list<string>
     */
    private function tailFile(string $path, int $lines): array
    {
        try {
            $content = @file_get_contents($path);
            if ($content === false || $content === '') {
                return [];
            }

            $parts = preg_split("/\r\n|\n|\r/", rtrim($content)) ?: [];

            return array_values(array_slice($parts, -$lines));
        } catch (\Throwable) {
            return [];
        }
    }
}
