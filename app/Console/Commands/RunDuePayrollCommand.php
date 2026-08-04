<?php

namespace App\Console\Commands;

use App\Services\Business\BusinessPayrollDueRunner;
use Illuminate\Console\Command;

class RunDuePayrollCommand extends Command
{
    protected $signature = 'payroll:run-due {--force : Ignore the opportunistic throttle}';

    protected $description = 'Process due scheduled payroll disbursement items (business balance)';

    public function handle(BusinessPayrollDueRunner $runner): int
    {
        $count = $runner->tick(force: (bool) $this->option('force'), minIntervalSeconds: 60);
        $this->info("Processed {$count} due payroll item(s).");

        return self::SUCCESS;
    }
}
