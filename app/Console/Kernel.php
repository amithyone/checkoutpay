<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Check if IMAP fetching is disabled
        $disableImap = \App\Models\Setting::get('disable_imap_fetching', false);

        // Monitor emails via IMAP (only if not disabled)
        if (!$disableImap) {
            $schedule->command('payment:monitor-emails')
                ->everyTenSeconds()
                ->withoutOverlapping()
                ->runInBackground();
        }

        // Always read emails directly from filesystem (more reliable)
        // This is the primary method when IMAP is disabled
        $schedule->command('payment:read-emails-direct --all')
            ->everyFifteenSeconds()
            ->withoutOverlapping()
            ->runInBackground();

        // Expire old payments every hour
        $schedule->command('payment:expire')
            ->hourly()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
