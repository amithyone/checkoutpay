<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Business\BusinessWebsiteSyncService;
use Illuminate\Console\Command;

class BackfillBusinessWebsiteWebhooks extends Command
{
    protected $signature = 'businesses:backfill-website-webhooks {--dry-run : Report changes without writing}';

    protected $description = 'Copy businesses.website and businesses.webhook_url onto business_websites rows';

    public function __construct(
        private BusinessWebsiteSyncService $sync,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $businesses = Business::query()
            ->with('websites')
            ->where(function ($q) {
                $q->where(function ($inner) {
                    $inner->whereNotNull('webhook_url')->where('webhook_url', '!=', '');
                })->orWhere(function ($inner) {
                    $inner->whereNotNull('website')->where('website', '!=', '');
                });
            })
            ->orderBy('id')
            ->get();

        $this->info('Businesses with a website or webhook on the businesses table: '.$businesses->count());

        $created = 0;
        $filled = 0;
        $skipped = 0;

        foreach ($businesses as $business) {
            if ($dryRun) {
                $this->line("Would sync business {$business->id} ({$business->name}) website=".($business->website ?: '-').' webhook='.($business->webhook_url ?: '-'));

                continue;
            }

            $stats = $this->sync->syncFromBusinessRecord($business, false);
            $created += $stats['created'];
            $filled += $stats['webhook_filled'];
            $skipped += $stats['skipped'];

            if ($stats['created'] > 0 || $stats['webhook_filled'] > 0) {
                $this->line("✓ Business {$business->id}: created {$stats['created']}, webhook filled {$stats['webhook_filled']}");
            }
        }

        $this->newLine();
        $this->info("Created website rows: {$created}");
        $this->info("Webhooks copied onto website rows: {$filled}");
        $this->info("Website rows left unchanged (already had a webhook): {$skipped}");

        if ($dryRun) {
            $this->warn('Dry run only. Run without --dry-run to write.');
        }

        return self::SUCCESS;
    }
}
