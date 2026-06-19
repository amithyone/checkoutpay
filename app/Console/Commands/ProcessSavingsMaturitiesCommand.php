<?php

namespace App\Console\Commands;

use App\Services\Consumer\ConsumerWalletSavingsService;
use Illuminate\Console\Command;

class ProcessSavingsMaturitiesCommand extends Command
{
    protected $signature = 'savings:process-maturities';

    protected $description = 'Unlock matured savings locks and pay interest to wallet balance';

    public function handle(ConsumerWalletSavingsService $savings): int
    {
        $result = $savings->processDueMaturities();
        $bonusResult = $savings->processDueFlexibleBonuses();
        $this->info(sprintf(
            'Processed %d maturities (%d failed), %d flexible bonuses (%d failed).',
            $result['processed'],
            $result['failed'],
            $bonusResult['processed'],
            $bonusResult['failed'],
        ));

        return self::SUCCESS;
    }
}
