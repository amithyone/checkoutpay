<?php

namespace App\Console\Commands;

use App\Models\Business;
use Illuminate\Console\Command;

class TriggerEndOfDayAutoWithdrawals extends Command
{
    protected $signature = 'withdrawals:trigger-end-of-day';

    protected $description = 'Run auto-withdrawal at end of day (5pm) for businesses that opted in';

    public function handle(): int
    {
        $this->info('Running end-of-day auto-withdrawals (5pm)...');

        $businesses = Business::where('auto_withdraw_end_of_day', true)
            ->whereNotNull('auto_withdraw_threshold')
            ->where('auto_withdraw_threshold', '>', 0)
            ->get();

        $triggered = 0;
        foreach ($businesses as $business) {
            if ($business->triggerAutoWithdrawal(true) !== null) {
                $triggered++;
                $this->line("  Triggered for business: {$business->name} (ID: {$business->id})");
            }
        }

        $this->info("Done. Triggered {$triggered} auto-withdrawal(s).");
        return 0;
    }
}
