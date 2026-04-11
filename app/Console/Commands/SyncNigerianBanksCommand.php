<?php

namespace App\Console\Commands;

use App\Services\BankDirectorySyncService;
use Illuminate\Console\Command;

class SyncNigerianBanksCommand extends Command
{
    protected $signature = 'banks:sync {--no-fallback : Fail if MevonPay getBankList is unavailable}';

    protected $description = 'Sync Nigerian banks from MevonPay getBankList (preferred) or config/banks.php fallback';

    public function handle(BankDirectorySyncService $sync): int
    {
        $result = $sync->sync(useFallbackIfApiFails: ! $this->option('no-fallback'));
        $this->info($result['message']);
        $this->line('Source: '.$result['source'].' · Upserted: '.$result['upserted']);

        if ($result['source'] === 'none') {
            return self::FAILURE;
        }
        if ($result['source'] === 'config_banks_php' && $result['upserted'] === 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
