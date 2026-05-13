<?php

namespace App\Console\Commands;

use App\Services\Credit\BusinessPeerLoanService;
use Illuminate\Console\Command;

class CollectBusinessLoanInstallments extends Command
{
    protected $signature = 'business-loans:collect-due {--frequency= : daily|weekly|monthly — only collect loans on that offer cadence. Omit to process all.}';

    protected $description = 'Collect peer loan repayments from borrower balances and credit lenders';

    public function handle(BusinessPeerLoanService $service): int
    {
        $frequency = $this->option('frequency');
        if ($frequency === null || $frequency === '') {
            $frequency = null;
        } elseif (! in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            $this->error('Invalid --frequency. Use daily, weekly, monthly, or omit for all.');

            return self::INVALID;
        }

        $c = $service->collectDue($frequency);
        $label = $frequency ?? 'all cadences';
        $this->info("Processed {$c} collection(s) ({$label}).");

        return self::SUCCESS;
    }
}
