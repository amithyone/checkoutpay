<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Jobs\SendWebhookNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentWebhook implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PaymentApproved $event): void
    {
        SendWebhookNotification::dispatch($event->payment);
    }
}
