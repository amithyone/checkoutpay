<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Mail\InvoiceReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendInvoiceReminders extends Command
{
    protected $signature = 'invoice:send-reminders
                            {--days-before=3 : Send reminder when due within this many days}
                            {--cooldown-hours=24 : Minimum hours between reminders for same invoice}';

    protected $description = 'Send reminder emails for invoices due soon or overdue (to client and business)';

    public function handle(): int
    {
        $daysBefore = (int) $this->option('days-before');
        $cooldownHours = (int) $this->option('cooldown-hours');
        $cooldown = now()->subHours($cooldownHours);

        $invoices = Invoice::with(['business'])
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->whereNotNull('due_date')
            ->where(function ($q) use ($daysBefore) {
                $q->whereDate('due_date', '<=', now()->addDays($daysBefore))
                  ->orWhereDate('due_date', '<', now());
            })
            ->where(function ($q) use ($cooldown) {
                $q->whereNull('last_reminder_sent_at')
                  ->orWhere('last_reminder_sent_at', '<', $cooldown);
            })
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices due for reminder.');
            return 0;
        }

        $this->info("Sending reminders for {$invoices->count()} invoice(s).");

        $sent = 0;
        foreach ($invoices as $invoice) {
            try {
                $isOverdue = $invoice->due_date->isPast();

                Mail::to($invoice->client_email)->send(new InvoiceReminder($invoice, $isOverdue, false));

                if ($invoice->business->email && $invoice->business->shouldReceiveEmailNotifications()) {
                    Mail::to($invoice->business->email)->send(new InvoiceReminder($invoice, $isOverdue, true));
                }

                $invoice->update(['last_reminder_sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                Log::error('Invoice reminder send failed', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("Failed to send reminder for invoice {$invoice->invoice_number}: {$e->getMessage()}");
            }
        }

        $this->info("Sent {$sent} reminder(s).");
        return 0;
    }
}
