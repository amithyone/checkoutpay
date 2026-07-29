<?php

namespace App\Console\Commands;

use App\Events\PaymentExpired;
use App\Models\Payment;
use App\Services\TransactionLogService;
use Illuminate\Console\Command;

class ExpirePayments extends Command
{
    protected $signature = 'payment:expire';

    protected $description = 'Expire pending payments that have passed their expiration time';

    public function handle(TransactionLogService $logService): void
    {
        $this->info('Checking for expired payments...');

        $expiredPayments = Payment::expired()->get();
        $legacyExpired = $this->legacyExpiredPendingPayments();

        $all = $expiredPayments->concat($legacyExpired)->unique('id');

        if ($all->isEmpty()) {
            $this->info('No expired payments found.');

            return;
        }

        $this->info("Found {$all->count()} expired payment(s)");

        foreach ($all as $payment) {
            if (! $payment->isPending() || $payment->shouldStayPendingIndefinitely()) {
                continue;
            }

            $payment->reject('Payment expired - no matching bank transfer received within time limit');

            $logService->logPaymentExpired($payment);

            $this->line("Expired payment: {$payment->transaction_id}");

            event(new PaymentExpired($payment));
        }

        $this->info('Expired payments processed successfully.');
    }

    /**
     * Pending rows created before auto-expiry existed: null expires_at but older than the admin window.
     *
     * @return \Illuminate\Support\Collection<int, Payment>
     */
    private function legacyExpiredPendingPayments()
    {
        $cutoff = now()->subMinutes(Payment::PENDING_MAX_AGE_MINUTES);

        return Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->whereNull('expires_at')
            ->where('created_at', '<=', $cutoff)
            ->whereNotIn('payment_source', [
                Payment::SOURCE_EXTERNAL_MEVONPAY,
                Payment::SOURCE_EXTERNAL_SLA,
                Payment::SOURCE_EXTERNAL_MAVONPAY,
                Payment::SOURCE_WHATSAPP_WALLET,
            ])
            ->get()
            ->filter(fn (Payment $payment) => ! $payment->shouldStayPendingIndefinitely());
    }
}
