<?php

namespace App\Console\Commands;

use App\Models\MevonPayDiscrepancyAlert;
use App\Services\MevonPay\MevonPayBalanceMonitorService;
use Illuminate\Console\Command;

class MevonCheckBalanceCommand extends Command
{
    protected $signature = 'mevon:check-balance';

    protected $description = 'Compare live Mevon balance to expected balance since monitoring baseline';

    public function handle(MevonPayBalanceMonitorService $monitor): int
    {
        $result = $monitor->checkNow(MevonPayDiscrepancyAlert::TRIGGER_SCHEDULED);

        if ($result['skipped'] ?? false) {
            $this->line($result['message']);

            return self::SUCCESS;
        }

        if (! ($result['ok'] ?? false)) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->line($result['message']);
        $this->line(sprintf(
            'Expected: ₦%s · Live: ₦%s · Variance: ₦%s',
            number_format((float) ($result['expected_balance'] ?? 0), 2),
            number_format((float) ($result['live_balance'] ?? 0), 2),
            number_format((float) ($result['variance_amount'] ?? 0), 2),
        ));

        if ($result['alert_created'] ?? false) {
            $this->warn('Discrepancy alert recorded (ID '.($result['alert_id'] ?? '?').').');
        }

        return self::SUCCESS;
    }
}
