<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Services\Broadcast\BroadcastSessionPaymentMatcher;

class MarkBroadcastSessionPaidOnPaymentApproved
{
    public function __construct(
        private readonly BroadcastSessionPaymentMatcher $matcher,
    ) {}

    public function handle(PaymentApproved $event): void
    {
        $this->matcher->handleApprovedPayment($event->payment);
    }
}
