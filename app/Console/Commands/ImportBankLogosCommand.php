<?php

namespace App\Console\Commands;

use App\Services\BankLogoService;
use Illuminate\Console\Command;

class ImportBankLogosCommand extends Command
{
    protected $signature = 'banks:import-logos {--force : Replace existing logos when a library match exists}';

    protected $description = 'Auto-map bank logos from resources/bank-logos/library onto banks (does not delete banks)';

    public function handle(BankLogoService $logos): int
    {
        $result = $logos->autoMap(force: (bool) $this->option('force'));
        $this->info("Mapped {$result['mapped']} · skipped {$result['skipped']} · missing library {$result['missing_library']}");

        return self::SUCCESS;
    }
}
