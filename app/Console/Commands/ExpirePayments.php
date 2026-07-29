<?php

namespace App\Console\Commands;

use App\Events\PaymentExpired;
use App\Models\Payment;
use App\Services\TransactionLogService;
use Illuminate\Console\Command;

class ExpirePayments extends Command
{
    protected $signature = 'payment:expire {--dry-run : List payments that would expire without rejecting them}';

    protected $description = 'Expire pending payments that have passed their expiration time or max age';

    public function handle(TransactionLogService $logService): int
    {
        $this->info('Checking for expired payments...');

        $candidates = Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotIn('payment_source', [
                Payment::SOURCE_EXTERNAL_MEVONPAY,
                Payment::SOURCE_EXTERNAL_SLA,
                Payment::SOURCE_EXTERNAL_MAVONPAY,
                Payment::SOURCE_WHATSAPP_WALLET,
            ])
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('expires_at')->where('expires_at', '<=', now());
                })->orWhere('created_at', '<=', now()->subMinutes(Payment::PENDING_MAX_AGE_MINUTES));
            })
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Payment $payment) => $payment->isStalePending());

        if ($candidates->isEmpty()) {
            $this->info('No expired payments found.');

            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} expired payment(s)");

        foreach ($candidates as $payment) {
            $age = (int) $payment->created_at->diffInMinutes(now());
            $line = "Expired payment: {$payment->transaction_id} (age {$age} min, expires_at="
                .($payment->expires_at?->toDateTimeString() ?? 'null').')';

            if ($this->option('dry-run')) {
                $this->line('[dry-run] '.$line);

                continue;
            }

            $payment->reject('Payment expired - no matching bank transfer received within time limit');

            $logService->logPaymentExpired($payment);

            $this->line($line);

            event(new PaymentExpired($payment));
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run only — no payments were rejected.');
        } else {
            $this->info('Expired payments processed successfully.');
        }

        return self::SUCCESS;
    }
}
