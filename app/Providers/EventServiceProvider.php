<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \App\Events\PaymentApproved::class => [
            \App\Listeners\SendPaymentWebhook::class,
            \App\Listeners\MarkInvoicePaidOnPaymentApproved::class,
            \App\Listeners\MarkRentalPaidOnPaymentApproved::class,
            \App\Listeners\CreateMembershipSubscriptionOnPaymentApproved::class,
            \App\Listeners\CreateNigtaxProUserFromPendingOnPaymentApproved::class,
            \App\Listeners\ProcessTicketOrderOnPayment::class,
            \App\Listeners\HandleNigtaxCertifiedPaymentApproved::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
