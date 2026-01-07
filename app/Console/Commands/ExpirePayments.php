<?php

namespace App\Console\Commands;

use App\Events\PaymentExpired;
use App\Models\Payment;
use App\Services\TransactionLogService;
use Illuminate\Console\Command;

class ExpirePayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending payments that have passed their expiration time';

    /**
     * Execute the console command.
     */
    public function handle(TransactionLogService $logService): void
    {
        $this->info('Checking for expired payments...');

        $expiredPayments = Payment::expired()->get();

        if ($expiredPayments->isEmpty()) {
            $this->info('No expired payments found.');
            return;
        }

        $this->info("Found {$expiredPayments->count()} expired payment(s)");

        foreach ($expiredPayments as $payment) {
            $payment->reject('Payment expired - no matching bank transfer received within time limit');
            
            // Log payment expired
            $logService->logPaymentExpired($payment);
            
            $this->line("Expired payment: {$payment->transaction_id}");

            // Dispatch event for webhook notification
            event(new PaymentExpired($payment));
        }

        $this->info('Expired payments processed successfully.');
    }
}
