<?php

namespace App\Services\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class SupportIntakeLockoutService
{
    public function maxWrongAccountAttempts(): int
    {
        return max(1, (int) config('support.intake_wrong_account_max_attempts', 5));
    }

    public function lockoutMinutes(): int
    {
        return max(1, (int) config('support.intake_lockout_minutes', 10));
    }

    public function clientKey(Request $request): string
    {
        $ip = (string) $request->ip();

        return 'support_intake:'.$ip;
    }

    /**
     * @return array{locked: bool, locked_until?: string, attempts: int}
     */
    public function status(Request $request): array
    {
        $key = $this->clientKey($request);
        $data = Cache::get($key);

        if (! is_array($data)) {
            return ['locked' => false, 'attempts' => 0];
        }

        $lockedUntil = isset($data['locked_until']) ? Carbon::parse($data['locked_until']) : null;
        if ($lockedUntil && $lockedUntil->isFuture()) {
            return [
                'locked' => true,
                'locked_until' => $lockedUntil->toIso8601String(),
                'attempts' => (int) ($data['attempts'] ?? 0),
            ];
        }

        if ($lockedUntil && $lockedUntil->isPast()) {
            Cache::forget($key);
        }

        return [
            'locked' => false,
            'attempts' => (int) ($data['attempts'] ?? 0),
        ];
    }

    /**
     * @return array{locked: bool, locked_until?: string, attempts: int, just_locked: bool}
     */
    public function recordWrongAccount(Request $request): array
    {
        $key = $this->clientKey($request);
        $max = $this->maxWrongAccountAttempts();
        $existing = $this->status($request);

        if ($existing['locked']) {
            return array_merge($existing, ['just_locked' => false]);
        }

        $attempts = $existing['attempts'] + 1;
        $ttl = ($this->lockoutMinutes() + 5) * 60;

        if ($attempts >= $max) {
            $lockedUntil = now()->addMinutes($this->lockoutMinutes());
            Cache::put($key, [
                'attempts' => $attempts,
                'locked_until' => $lockedUntil->toIso8601String(),
            ], $ttl);

            return [
                'locked' => true,
                'locked_until' => $lockedUntil->toIso8601String(),
                'attempts' => $attempts,
                'just_locked' => true,
            ];
        }

        Cache::put($key, ['attempts' => $attempts], $ttl);

        return [
            'locked' => false,
            'attempts' => $attempts,
            'just_locked' => false,
        ];
    }

    public function clearForClient(Request $request): void
    {
        Cache::forget($this->clientKey($request));
    }

    public function remainingAttempts(int $currentAttempts): int
    {
        return max(0, $this->maxWrongAccountAttempts() - $currentAttempts);
    }
}
