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

        // Master email processing cron (3 sequential steps):
        // STEP 1: Fetch emails from filesystem
        // STEP 2: Fill sender_name from text_body if null
        // STEP 3: Match transactions
        // This is the primary method when IMAP is disabled
        $schedule->call(function () {
            // Use HTTP client to call the master cron route
            try {
                $url = url('/cron/process-emails');
                \Illuminate\Support\Facades\Http::timeout(300)->get($url);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Master email processing cron failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        })
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
