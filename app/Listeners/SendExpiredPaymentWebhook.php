<?php

namespace App\Listeners;

use App\Events\PaymentExpired;
use App\Jobs\SendWebhookNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendExpiredPaymentWebhook implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PaymentExpired $event): void
    {
        // Send webhook for expired payment
        SendWebhookNotification::dispatch($event->payment);
    }
}
