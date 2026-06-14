<?php

namespace App\Console\Commands;

use App\Services\Business\BusinessLoanTransactionService;
use Illuminate\Console\Command;

class BackfillBusinessLoanTransactions extends Command
{
    protected $signature = 'business-loans:backfill-transactions';

    protected $description = 'Create business transaction history rows for existing peer loan repayments';

    public function handle(BusinessLoanTransactionService $service): int
    {
        $created = $service->backfillMissingRepayments();
        $this->info("Backfilled {$created} loan repayment transaction row(s).");

        return self::SUCCESS;
    }
}
