<?php

namespace App\Services\Business;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Opportunistic due-payroll runner for environments without a reliable cron.
 * Safe to call from HTTP terminate / other frequent jobs — throttled + locked.
 */
final class BusinessPayrollDueRunner
{
    public const CACHE_TICK_KEY = 'payroll:due:last_tick';

    public const CACHE_LOCK_KEY = 'payroll:due:lock';

    public function __construct(
        private BusinessPayrollService $payroll,
    ) {}

    /**
     * @return int Number of items processed (0 when skipped / nothing due)
     */
    public function tick(bool $force = false, int $minIntervalSeconds = 60): int
    {
        if (! $force) {
            $last = Cache::get(self::CACHE_TICK_KEY);
            if (is_numeric($last) && (time() - (int) $last) < max(15, $minIntervalSeconds)) {
                return 0;
            }
        }

        $lock = Cache::lock(self::CACHE_LOCK_KEY, 50);
        if (! $lock->get()) {
            return 0;
        }

        try {
            Cache::put(self::CACHE_TICK_KEY, time(), now()->addHours(6));
            $count = $this->payroll->runDueItems();
            if ($count > 0) {
                Log::info('payroll_due_runner_processed', ['count' => $count]);
            }

            return $count;
        } catch (\Throwable $e) {
            Log::warning('payroll_due_runner_failed', ['error' => $e->getMessage()]);

            return 0;
        } finally {
            optional($lock)->release();
        }
    }
}
