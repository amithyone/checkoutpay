<?php

namespace App\Console\Commands;

use App\Services\Credit\BusinessPeerLoanService;
use Illuminate\Console\Command;

class CollectBusinessLoanInstallments extends Command
{
    protected $signature = 'business-loans:collect-due';

    protected $description = 'Collect peer loan repayments from borrower balances and credit lenders';

    public function handle(BusinessPeerLoanService $service): int
    {
        $c = $service->collectDue();
        $this->info("Processed {$c} collection(s).");

        return self::SUCCESS;
    }
}
