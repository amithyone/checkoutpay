<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Credit\OverdraftInstallmentService;
use Illuminate\Console\Command;

class ProcessOverdraftInstallments extends Command
{
    protected $signature = 'overdraft:process-installments';

    protected $description = 'Sync overdraft installment paid/overdue status from business balance recovery';

    public function handle(OverdraftInstallmentService $service): int
    {
        $n = 0;
        Business::query()
            ->whereNotNull('overdraft_repayment_started_at')
            ->chunkById(100, function ($chunk) use ($service, &$n) {
                foreach ($chunk as $business) {
                    $service->syncInstallmentStatuses($business->fresh());
                    $n++;
                }
            });

        $this->info("Synced installments for {$n} business(es).");

        return self::SUCCESS;
    }
}
