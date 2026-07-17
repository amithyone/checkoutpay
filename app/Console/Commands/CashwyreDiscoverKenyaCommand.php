<?php

namespace App\Console\Commands;

use App\Services\Cashwyre\CashwyreKenyaDiscoveryService;
use App\Services\Region\RegionCapabilitiesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CashwyreDiscoverKenyaCommand extends Command
{
    protected $signature = 'cashwyre:discover-kenya
                            {--write-config : Also write results into config/cashwyre_kenya_capabilities.php}';

    protected $description = 'Probe Cashwyre CountryBank APIs for Kenya (KES) and store capability flags for the app';

    public function handle(CashwyreKenyaDiscoveryService $discovery, RegionCapabilitiesService $regions): int
    {
        $this->info('Discovering Cashwyre Kenya (KE/KES) capabilities…');

        $snapshot = $discovery->discover();
        $regions->storeKenyaCashwyreSnapshot($snapshot);

        $this->table(
            ['Flag', 'Value'],
            [
                ['configured', $snapshot['configured'] ? 'yes' : 'no'],
                ['bank_payout', $snapshot['bank_payout'] ? 'yes' : 'no'],
                ['mpesa_payout', $snapshot['mpesa_payout'] ? 'yes' : 'no'],
                ['mpesa_collection', $snapshot['mpesa_collection'] ? 'yes' : 'no'],
                ['bank_collection', $snapshot['bank_collection'] ? 'yes' : 'no'],
                ['bills', $snapshot['bills'] ? 'yes' : 'no'],
                ['airtime', $snapshot['airtime'] ? 'yes' : 'no'],
                ['banks_payout_count', (string) ($snapshot['banks_payout_count'] ?? 0)],
                ['wallets_payout_count', (string) ($snapshot['wallets_payout_count'] ?? 0)],
                ['banks_collection_count', (string) ($snapshot['banks_collection_count'] ?? 0)],
                ['wallets_collection_count', (string) ($snapshot['wallets_collection_count'] ?? 0)],
            ]
        );

        if (! empty($snapshot['sample_bank_codes'])) {
            $this->line('Sample bank codes: '.implode(', ', $snapshot['sample_bank_codes']));
        }
        if (! empty($snapshot['sample_wallet_codes'])) {
            $this->line('Sample wallet codes: '.implode(', ', $snapshot['sample_wallet_codes']));
        }
        if (! empty($snapshot['notes'])) {
            $this->warn($snapshot['notes']);
        }
        if (! empty($snapshot['last_error'])) {
            $this->error($snapshot['last_error']);
        }

        if ($this->option('write-config')) {
            $export = $snapshot;
            unset($export['raw']);
            $php = "<?php\n\nreturn ".var_export($export, true).";\n";
            File::put(config_path('cashwyre_kenya_capabilities.php'), $php);
            $this->info('Wrote config/cashwyre_kenya_capabilities.php');
        }

        $this->info('Cached under cashwyre_kenya_capabilities (forever). RegionCapabilitiesService will pick this up.');

        return self::SUCCESS;
    }
}
