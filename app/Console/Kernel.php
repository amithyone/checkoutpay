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
        $schedule->command('banks:sync')->weekly();
        $schedule->command('whatsapp-wallet:expire-pending-p2p')->everyFiveMinutes();
        $schedule->command('overdraft:charge-interest')->weekly();
        $schedule->command('overdraft:process-installments')->daily();
        $schedule->command('business-loans:collect-due')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
