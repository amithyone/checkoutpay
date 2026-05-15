<?php

namespace App\Console\Commands;

use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletPayCodeService;
use Illuminate\Console\Command;

class BackfillWalletPayCodesCommand extends Command
{
    protected $signature = 'wallet:backfill-pay-codes {--chunk=200 : Rows per batch}';

    protected $description = 'Assign unique 5-digit pay_code to whatsapp_wallets that do not have one';

    public function handle(ConsumerWalletPayCodeService $payCodes): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $assigned = 0;

        WhatsappWallet::query()
            ->whereNull('pay_code')
            ->orderBy('id')
            ->chunkById($chunk, function ($wallets) use ($payCodes, &$assigned) {
                foreach ($wallets as $wallet) {
                    $payCodes->ensureForWallet($wallet);
                    $assigned++;
                }
                $this->info("Assigned {$assigned} pay codes so far…");
            });

        $this->info("Done. Assigned pay_code to {$assigned} wallet(s).");

        return self::SUCCESS;
    }
}
