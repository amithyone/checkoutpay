<?php

namespace App\Services\Quarantine;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

/**
 * Fail-closed site quarantine when DB secrets/fingerprint look hijacked.
 * Lock state is on local disk — not in MySQL — so an empty attacker DB cannot clear it.
 */
class QuarantineService
{
    public const REASON_ALREADY_LOCKED = 'already_locked';

    public const REASON_HOST_NOT_ALLOWED = 'db_host_not_allowed';

    public const REASON_DATABASE_MISMATCH = 'db_database_mismatch';

    public const REASON_CONNECTION_FAILED = 'db_connection_failed';

    public const REASON_TABLE_MISSING = 'required_table_missing';

    public const REASON_FLOOR_PAYMENTS = 'payments_below_floor';

    public const REASON_FLOOR_BUSINESSES = 'businesses_below_floor';

    public const REASON_FLOOR_ADMINS = 'admins_below_floor';

    private const HEALTHY_CACHE_KEY = 'quarantine:last_healthy_at';

    public function isEnabled(): bool
    {
        return (bool) config('checkout.quarantine.enabled', false);
    }

    public function lockPath(): string
    {
        return storage_path(config('checkout.quarantine.lock_relative_path', 'framework/quarantine.lock'));
    }

    public function baselinePath(): string
    {
        return storage_path(config('checkout.quarantine.baseline_relative_path', 'app/quarantine-baseline.json'));
    }

    public function isLocked(): bool
    {
        return is_file($this->lockPath());
    }

    /**
     * @return array{active: bool, enabled: bool, reasons: list<string>, lock: ?array<string, mixed>}
     */
    public function status(): array
    {
        $lock = null;
        if ($this->isLocked()) {
            $raw = @file_get_contents($this->lockPath());
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $lock = is_array($decoded) ? $decoded : ['raw' => true];
        }

        $reasons = [];
        if ($this->isLocked()) {
            $reasons[] = self::REASON_ALREADY_LOCKED;
            if (isset($lock['reasons']) && is_array($lock['reasons'])) {
                foreach ($lock['reasons'] as $r) {
                    if (is_string($r) && $r !== self::REASON_ALREADY_LOCKED) {
                        $reasons[] = $r;
                    }
                }
            }
        } elseif ($this->isEnabled()) {
            $reasons = $this->evaluateFingerprint(false);
        }

        return [
            'active' => $this->isLocked() || ($this->isEnabled() && $reasons !== []),
            'enabled' => $this->isEnabled(),
            'reasons' => array_values(array_unique($reasons)),
            'lock' => $lock,
        ];
    }

    /**
     * Run checks; trip lock if needed. Returns true when site must stay locked out.
     */
    public function guard(): bool
    {
        if (! $this->isEnabled()) {
            return $this->isLocked();
        }

        if ($this->isLocked()) {
            return true;
        }

        $interval = (int) config('checkout.quarantine.check_interval_seconds', 60);
        $lastHealthy = Cache::get(self::HEALTHY_CACHE_KEY);
        if (is_int($lastHealthy) && (time() - $lastHealthy) < $interval) {
            return false;
        }

        $reasons = $this->evaluateFingerprint(true);
        if ($reasons === []) {
            Cache::put(self::HEALTHY_CACHE_KEY, time(), $interval + 30);

            return false;
        }

        $this->trip($reasons);

        return true;
    }

    /**
     * Same as guard but always evaluates (for artisan / status).
     *
     * @return list<string>
     */
    public function evaluateNow(): array
    {
        if ($this->isLocked()) {
            return [self::REASON_ALREADY_LOCKED];
        }

        if (! $this->isEnabled()) {
            return [];
        }

        return $this->evaluateFingerprint(true);
    }

    /**
     * @param  list<string>  $reasons
     */
    public function trip(array $reasons): void
    {
        $path = $this->lockPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $payload = [
            'tripped_at' => now()->toIso8601String(),
            'reasons' => array_values($reasons),
            'db_host' => (string) config('database.connections.'.config('database.default').'.host'),
            'db_database' => (string) config('database.connections.'.config('database.default').'.database'),
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($path, 0640);

        Cache::forget(self::HEALTHY_CACHE_KEY);

        Log::critical('quarantine_tripped', [
            'reasons' => $reasons,
            'db_host' => $payload['db_host'],
            'db_database' => $payload['db_database'],
        ]);
    }

    public function clearWithCode(string $code): bool
    {
        $expected = (string) config('checkout.quarantine.unlock_code', '');
        if ($expected === '' || ! hash_equals($expected, $code)) {
            Log::warning('quarantine_unlock_failed');

            return false;
        }

        return $this->clearLock();
    }

    public function clearLock(): bool
    {
        $path = $this->lockPath();
        if (is_file($path)) {
            @unlink($path);
        }
        Cache::forget(self::HEALTHY_CACHE_KEY);
        Log::critical('quarantine_cleared');

        return ! is_file($path);
    }

    /**
     * Capture current DB identity + counts into baseline JSON and suggest floors.
     *
     * @return array<string, mixed>
     */
    public function writeBaseline(): array
    {
        $connection = config('database.default');
        $host = (string) config("database.connections.{$connection}.host");
        $database = (string) config("database.connections.{$connection}.database");

        $payments = Schema::hasTable('payments') ? (int) DB::table('payments')->count() : 0;
        $businesses = Schema::hasTable('businesses') ? (int) DB::table('businesses')->count() : 0;
        $admins = Schema::hasTable('admins') ? (int) DB::table('admins')->count() : 0;

        $baseline = [
            'captured_at' => now()->toIso8601String(),
            'db_host' => $host,
            'db_database' => $database,
            'counts' => [
                'payments' => $payments,
                'businesses' => $businesses,
                'admins' => $admins,
            ],
            'suggested_floors' => [
                'QUARANTINE_MIN_PAYMENTS' => (int) max(0, floor($payments * 0.95)),
                'QUARANTINE_MIN_BUSINESSES' => (int) max(0, floor($businesses * 0.9)),
                'QUARANTINE_MIN_ADMINS' => (int) max(1, min($admins, max(1, $admins - 1))),
            ],
        ];

        $path = $this->baselinePath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($path, 0640);

        return $baseline;
    }

    /**
     * @return list<string>
     */
    private function evaluateFingerprint(bool $probeDatabase): array
    {
        $reasons = [];

        $connection = config('database.default');
        $host = strtolower(trim((string) config("database.connections.{$connection}.host")));
        $database = strtolower(trim((string) config("database.connections.{$connection}.database")));

        $allowedHosts = config('checkout.quarantine.allowed_db_hosts', []);
        if ($allowedHosts !== [] && ! in_array($host, $allowedHosts, true)) {
            $reasons[] = self::REASON_HOST_NOT_ALLOWED;
        }

        $allowedDb = strtolower(trim((string) config('checkout.quarantine.allowed_db_database', '')));
        if ($allowedDb !== '' && $database !== $allowedDb && $database !== ':memory:') {
            $reasons[] = self::REASON_DATABASE_MISMATCH;
        }

        // Host/database alone is enough to trip without querying remote attacker DB
        if ($reasons !== []) {
            return $reasons;
        }

        if (! $probeDatabase) {
            return $reasons;
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $reasons[] = self::REASON_CONNECTION_FAILED;

            return $reasons;
        }

        foreach (config('checkout.quarantine.required_tables', []) as $table) {
            if (! is_string($table) || $table === '') {
                continue;
            }
            if (! Schema::hasTable($table)) {
                $reasons[] = self::REASON_TABLE_MISSING;

                return $reasons;
            }
        }

        $minPayments = (int) config('checkout.quarantine.min_payments', 0);
        $minBusinesses = (int) config('checkout.quarantine.min_businesses', 0);
        $minAdmins = (int) config('checkout.quarantine.min_admins', 0);

        if ($minPayments > 0 && Schema::hasTable('payments')) {
            if ((int) DB::table('payments')->count() < $minPayments) {
                $reasons[] = self::REASON_FLOOR_PAYMENTS;
            }
        }
        if ($minBusinesses > 0 && Schema::hasTable('businesses')) {
            if ((int) DB::table('businesses')->count() < $minBusinesses) {
                $reasons[] = self::REASON_FLOOR_BUSINESSES;
            }
        }
        if ($minAdmins > 0 && Schema::hasTable('admins')) {
            if ((int) DB::table('admins')->count() < $minAdmins) {
                $reasons[] = self::REASON_FLOOR_ADMINS;
            }
        }

        return $reasons;
    }

    /**
     * Commands that must not run while quarantined or when fingerprint would trip.
     *
     * @return list<string>
     */
    public static function blockedArtisanCommands(): array
    {
        return [
            'migrate',
            'migrate:fresh',
            'migrate:refresh',
            'migrate:reset',
            'migrate:rollback',
            'migrate:status',
            'migrate:install',
            'db:wipe',
            'db:seed',
            'db:show',
            'db:table',
            'db:monitor',
            'schema:dump',
            'schema:load',
        ];
    }

    public function shouldBlockArtisan(string $commandName): bool
    {
        $name = strtolower(trim($commandName));
        $destructive = str_starts_with($name, 'migrate')
            || str_starts_with($name, 'db:')
            || str_starts_with($name, 'schema:');

        if (! $destructive) {
            return false;
        }

        if ($this->isLocked()) {
            return true;
        }

        if (! $this->isEnabled()) {
            return false;
        }

        return $this->evaluateNow() !== [];
    }
}
