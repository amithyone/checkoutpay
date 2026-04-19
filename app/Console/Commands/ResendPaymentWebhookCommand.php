<?php

namespace App\Console\Commands;

use App\Jobs\SendWebhookNotification;
use App\Models\Payment;
use Illuminate\Console\Command;

class ResendPaymentWebhookCommand extends Command
{
    protected $signature = 'webhooks:resend
                            {transaction_id? : payments.transaction_id — resend this approved payment}
                            {--latest-pending : Most recent approved payment with webhook null/pending/failed}
                            {--latest-approved : Most recent approved payment (even if webhook already sent)}';

    protected $description = 'Synchronously POST payment.approved webhook for one approved payment (bypasses queue).';

    public function handle(): int
    {
        $tid = $this->argument('transaction_id');

        if ($tid) {
            $payment = Payment::withTrashed()
                ->where('transaction_id', $tid)
                ->first();
            if (! $payment) {
                $this->error("No payment with transaction_id: {$tid}");

                return self::FAILURE;
            }
        } elseif ($this->option('latest-pending')) {
            $payment = Payment::query()
                ->where('status', Payment::STATUS_APPROVED)
                ->where(function ($q) {
                    $q->whereNull('webhook_status')
                        ->orWhere('webhook_status', 'pending')
                        ->orWhere('webhook_status', 'failed');
                })
                ->orderByDesc('matched_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();
            if (! $payment) {
                $this->error('No approved payment found with webhook_status null, pending, or failed.');

                return self::FAILURE;
            }
        } elseif ($this->option('latest-approved')) {
            $payment = Payment::query()
                ->where('status', Payment::STATUS_APPROVED)
                ->orderByDesc('matched_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();
            if (! $payment) {
                $this->error('No approved payment found.');

                return self::FAILURE;
            }
        } else {
            $this->error('Provide transaction_id, --latest-pending, or --latest-approved');

            return self::FAILURE;
        }

        if (! $payment->isApproved()) {
            $this->error("Payment {$payment->transaction_id} is not approved (status: {$payment->status}).");

            return self::FAILURE;
        }

        $this->info("Sending webhook for {$payment->transaction_id} (id {$payment->id})…");
        SendWebhookNotification::dispatchSync($payment);
        $payment->refresh();

        $this->info('Done. webhook_status='.($payment->webhook_status ?? 'null'));

        return self::SUCCESS;
    }
}
