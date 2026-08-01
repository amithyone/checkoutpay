<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Credit\OverdraftEligibilityService;
use Illuminate\Console\Command;

class SyncOverdraftVolumeEligibilityCommand extends Command
{
    protected $signature = 'overdraft:sync-volume-eligibility {--business_id=}';

    protected $description = 'Recompute 90-day business volume and overdraft eligibility tiers';

    public function handle(OverdraftEligibilityService $eligibility): int
    {
        $query = Business::query()->where('is_active', true);
        if ($this->option('business_id')) {
            $query->where('id', (int) $this->option('business_id'));
        }

        $count = 0;
        $query->orderBy('id')->chunkById(100, function ($businesses) use ($eligibility, &$count) {
            foreach ($businesses as $business) {
                $eligibility->syncBusiness($business);
                $count++;
            }
        });

        $this->info("Synced overdraft volume for {$count} business(es).");

        return self::SUCCESS;
    }
}
