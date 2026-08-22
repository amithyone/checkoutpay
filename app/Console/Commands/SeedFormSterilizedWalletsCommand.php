<?php

namespace App\Console\Commands;

use App\Services\WalletImport\FormCsvWalletSeedService;
use Illuminate\Console\Command;

class SeedFormSterilizedWalletsCommand extends Command
{
    protected $signature = 'wallet:seed-form-sterilized
        {file? : Path to sterilized JSONL}
        {--dry-run : Report what would be created (default if --apply not set)}
        {--apply : Actually create wallets}
        {--only-ok=1 : Only seed status=ok rows (set 0 to include needs_review)}
        {--provision-tier2 : Queue Mevon Tier-2 VA for BVN rows after create}';

    protected $description = 'Seed sterilized form JSONL into whatsapp_wallets (skip existing phones; Tier 1 by default)';

    public function handle(FormCsvWalletSeedService $seeder): int
    {
        $file = $this->argument('file')
            ?: base_path('database/backups/imports/sterilized/form-responses-sterilized.jsonl');

        if (! is_readable($file)) {
            $this->error('JSONL not readable: '.$file);
            $this->line('Run: php artisan wallet:sterilize-form-csv');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply || (bool) $this->option('dry-run');
        if ($apply && $this->option('dry-run')) {
            // Explicit --apply wins unless user only wants dry-run — prefer apply when both set.
            $dryRun = false;
        }
        if (! $apply) {
            $dryRun = true;
        }

        $onlyOk = filter_var($this->option('only-ok'), FILTER_VALIDATE_BOOL);
        $provision = (bool) $this->option('provision-tier2');

        if ($provision && $dryRun) {
            $this->warn('--provision-tier2 is ignored during dry-run.');
            $provision = false;
        }

        if ($apply) {
            $this->warn('APPLY mode: creating wallets in the database.');
            if ($provision) {
                $this->warn('Will queue Mevon Tier-2 provisioning for BVN rows.');
            }
        } else {
            $this->info('Dry-run only (pass --apply to write).');
        }

        $stats = $seeder->seedFromJsonl($file, $apply && ! $dryRun, $onlyOk, $provision);

        $this->table(
            ['metric', 'count'],
            [
                ['would_create', $stats['would_create']],
                ['created', $stats['created']],
                ['skipped_existing', $stats['skipped_existing']],
                ['skipped_reject', $stats['skipped_reject']],
                ['skipped_needs_review', $stats['skipped_needs_review']],
                ['provision_queued', $stats['provision_queued']],
                ['provision_failed', $stats['provision_failed']],
                ['errors', count($stats['errors'])],
            ]
        );

        foreach (array_slice($stats['errors'], 0, 20) as $err) {
            $this->line('  · '.$err);
        }
        if (count($stats['errors']) > 20) {
            $this->line('  … and '.(count($stats['errors']) - 20).' more');
        }

        return self::SUCCESS;
    }
}
