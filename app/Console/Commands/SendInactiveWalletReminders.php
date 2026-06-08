<?php

namespace App\Console\Commands;

use App\Services\Consumer\ConsumerWalletInactiveReminderService;
use Illuminate\Console\Command;

class SendInactiveWalletReminders extends Command
{
    protected $signature = 'wallet:send-inactive-reminders {--slot= : morning or evening}';

    protected $description = 'Remind wallets with balance and no activity today (push + WhatsApp), twice daily';

    public function handle(ConsumerWalletInactiveReminderService $reminders): int
    {
        $slot = (string) $this->option('slot');
        if ($slot === '') {
            $slot = (string) ($reminders->inferSlotFromNow() ?? '');
        }

        if ($slot === '') {
            $this->warn('No slot resolved. Pass --slot=morning or --slot=evening.');

            return 0;
        }

        $stats = $reminders->sendForSlot($slot);

        $this->info(sprintf(
            'Slot %s: %d wallet(s), %d push, %d WhatsApp, %d skipped (already sent).',
            $slot,
            $stats['wallets'],
            $stats['push'],
            $stats['whatsapp'],
            $stats['skipped'],
        ));

        return 0;
    }
}
