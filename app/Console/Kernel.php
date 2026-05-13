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
        // Peer loans: one scheduler pass per repayment rhythm (offer frequency). Lump-sum loans run with the daily pass.
        $schedule->command('business-loans:collect-due --frequency=daily')
            ->dailyAt('06:30')
            ->withoutOverlapping(45);
        $schedule->command('business-loans:collect-due --frequency=weekly')
            ->weeklyOn(1, '07:00')
            ->withoutOverlapping(120);
        $schedule->command('business-loans:collect-due --frequency=monthly')
            ->monthlyOn(1, '07:30')
            ->withoutOverlapping(180);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
