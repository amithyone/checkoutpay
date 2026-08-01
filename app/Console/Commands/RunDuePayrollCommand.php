<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Business\BusinessPayrollService;
use Illuminate\Console\Command;

class RunDuePayrollCommand extends Command
{
    protected $signature = 'payroll:run-due';

    protected $description = 'Process due scheduled payroll disbursement items';

    public function handle(BusinessPayrollService $payroll): int
    {
        $count = $payroll->runDueItems();
        $this->info("Processed {$count} due payroll item(s).");

        return self::SUCCESS;
    }
}
