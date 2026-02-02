<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Jobs\SendWebhookNotification;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPaymentWebhook implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PaymentApproved $event): void
    {
        try {
            // Reload payment to ensure it's fresh and serializable
            // This avoids serialization issues when the listener is queued
            $payment = Payment::find($event->payment->id);
            
            if (!$payment) {
                Log::error('Payment not found when dispatching webhook', [
                    'payment_id' => $event->payment->id ?? 'unknown',
                    'transaction_id' => $event->payment->transaction_id ?? 'unknown',
                ]);
                return;
            }
            
            // Dispatch webhook job
            SendWebhookNotification::dispatch($payment);
        } catch (\Exception $e) {
            Log::error('Error dispatching webhook notification', [
                'payment_id' => $event->payment->id ?? 'unknown',
                'transaction_id' => $event->payment->transaction_id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }
    
    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendPaymentWebhook listener failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
