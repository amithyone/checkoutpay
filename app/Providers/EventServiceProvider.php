<?php

namespace App\Providers;

use App\Events\PaymentApproved;
use App\Events\PaymentExpired;
use App\Listeners\CreateMembershipSubscriptionOnPaymentApproved;
use App\Listeners\MarkInvoicePaidOnPaymentApproved;
use App\Listeners\ProcessTicketOrderOnPayment;
use App\Listeners\SendExpiredPaymentWebhook;
use App\Listeners\SendPaymentWebhook;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        PaymentApproved::class => [
            CreateMembershipSubscriptionOnPaymentApproved::class,
            MarkInvoicePaidOnPaymentApproved::class,
            ProcessTicketOrderOnPayment::class,
            SendPaymentWebhook::class,
        ],
        PaymentExpired::class => [
            SendExpiredPaymentWebhook::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
