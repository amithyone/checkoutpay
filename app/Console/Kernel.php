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
        // Monitor emails every 10 seconds (more frequent for faster detection)
        $schedule->command('payment:monitor-emails')
            ->everyTenSeconds()
            ->withoutOverlapping()
            ->runInBackground();

        // Also read emails directly from filesystem every 15 seconds
        // This ensures we catch emails even if IMAP doesn't work
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
