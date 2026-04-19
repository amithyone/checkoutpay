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
                            {--latest-approved : Most recent approved payment (even if webhook already sent)}
                            {--show-response : Print HTTP status and response body preview from the merchant endpoint}';

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

        if ($this->option('show-response')) {
            $log = SendWebhookNotification::$lastHttpDeliveryLog;
            if ($log === null || $log === []) {
                $this->warn('No HTTP delivery log captured (non-approved skip or empty URL list).');
            } else {
                $this->newLine();
                $this->info('Merchant endpoint response(s):');
                $this->line(json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            }
        }

        return self::SUCCESS;
    }
}
