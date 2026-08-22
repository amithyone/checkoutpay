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
        $schedule->command('vtu:reconcile-pending-electricity')
            ->everyThreeMinutes()
            ->withoutOverlapping(10);
        $schedule->command('wallet:send-inactive-reminders --slot=morning')
            ->dailyAt('09:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30);
        $schedule->command('wallet:send-inactive-reminders --slot=evening')
            ->dailyAt('18:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30);
        $schedule->command('wallet:referral-leaderboard')
            ->monthlyOn(1, '00:30')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(60);
        $schedule->command('overdraft:charge-interest')->weekly();
        $schedule->command('overdraft:process-installments')->daily();
        $schedule->command('overdraft:sync-volume-eligibility')->dailyAt('02:15');
        $schedule->command('payroll:run-due')
            ->everyFiveMinutes()
            ->withoutOverlapping(4);
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
        $schedule->command('savings:process-maturities')
            ->dailyAt('02:00')
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(30);
        $schedule->command('savings:send-reminders')
            ->hourly()
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(15);
        $schedule->command('save-together:process-deadlines')
            ->hourly()
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(15);

        $schedule->command('rentals:send-return-reminders')
            ->everyFifteenMinutes()
            ->timezone('Africa/Lagos')
            ->withoutOverlapping(10);

        $schedule->command('payment:expire')
            ->everyFiveMinutes()
            ->withoutOverlapping(10);

        $schedule->command('mevon:check-balance')
            ->everyFifteenMinutes()
            ->withoutOverlapping(10);

        if (config('virtual_card.fx_hourly_capture_enabled', false)) {
            $schedule->command('virtual-cards:capture-fx-rates')
                ->hourly()
                ->timezone('Africa/Lagos')
                ->withoutOverlapping(10);
        }

        if ((bool) config('live_sync.incremental_cron', false)
            && (bool) config('services.live_sync.transmit_enabled', false)) {
            $minutes = max(1, (int) config('live_sync.incremental_cron_minutes', 5));
            $schedule->command('live-sync:incremental --sync')
                ->cron("*/{$minutes} * * * *")
                ->withoutOverlapping(10);
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
