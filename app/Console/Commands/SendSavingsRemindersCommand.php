<?php

namespace App\Console\Commands;

use App\Services\Consumer\ConsumerWalletSavingsService;
use Illuminate\Console\Command;

class SendSavingsRemindersCommand extends Command
{
    protected $signature = 'savings:send-reminders';

    protected $description = 'Send scheduled savings reminders to CheckoutNow users';

    public function handle(ConsumerWalletSavingsService $savings): int
    {
        $result = $savings->sendDueReminders();
        $this->info('Sent '.$result['sent'].' savings reminders.');

        return self::SUCCESS;
    }
}
