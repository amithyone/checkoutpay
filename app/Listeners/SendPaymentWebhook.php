<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Jobs\SendWebhookNotification;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Sends the payment.approved webhook immediately when a payment is matched/approved.
 * Uses dispatchSync so the webhook is sent in the same request—no waiting for queue/cron.
 */
class SendPaymentWebhook
{
    /**
     * Handle the event: try to send webhook immediately (sync). If that fails, queue it so the worker sends it when it runs.
     */
    public function handle(PaymentApproved $event): void
    {
        $payment = Payment::find($event->payment->id);

        if (!$payment) {
            Log::error('Payment not found when sending webhook', [
                'payment_id' => $event->payment->id ?? 'unknown',
                'transaction_id' => $event->payment->transaction_id ?? 'unknown',
            ]);
            return;
        }

        try {
            // Try to send immediately so merchant gets it at match time—no wait for queue/cron
            SendWebhookNotification::dispatchSync($payment);
        } catch (\Exception $e) {
            Log::error('Sync webhook failed, queuing for retry when queue runs', [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'error' => $e->getMessage(),
            ]);
            // Fallback: queue it so when the queue worker runs it will send (second chance)
            SendWebhookNotification::dispatch($payment);
        }
    }
}
