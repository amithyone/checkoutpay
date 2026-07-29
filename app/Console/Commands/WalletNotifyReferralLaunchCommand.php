<?php

namespace App\Console\Commands;

use App\Services\Consumer\WalletReferralLaunchNotificationService;
use Illuminate\Console\Command;

class WalletNotifyReferralLaunchCommand extends Command
{
    protected $signature = 'wallet:notify-referral-launch
                            {--dry-run : Count recipients without sending}
                            {--force : Include wallets already notified}
                            {--email-only : Send email only}
                            {--push-only : Send push only}';

    protected $description = 'Email and/or push wallet users about the referral programme (Profile → Refer and Earn)';

    public function handle(WalletReferralLaunchNotificationService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $emailOnly = (bool) $this->option('email-only');
        $pushOnly = (bool) $this->option('push-only');

        if ($emailOnly && $pushOnly) {
            $this->error('Use --email-only or --push-only, not both.');

            return self::FAILURE;
        }

        $sendEmail = ! $pushOnly;
        $sendPush = ! $emailOnly;

        try {
            if ($dryRun) {
                $counts = $service->sendAll(true, $force, $sendEmail, $sendPush);
            } else {
                if (! $this->confirm('Send referral launch notifications now?', ! app()->environment('production'))) {
                    $this->info('Cancelled.');

                    return self::SUCCESS;
                }
                $counts = $service->sendAll(false, $force, $sendEmail, $sendPush);
            }
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Count'],
            collect($counts)->map(fn ($v, $k) => [str_replace('_', ' ', (string) $k), $v])->values()->all()
        );

        if ($dryRun) {
            $this->info('Dry run only — nothing was sent.');
        }

        return self::SUCCESS;
    }
}
