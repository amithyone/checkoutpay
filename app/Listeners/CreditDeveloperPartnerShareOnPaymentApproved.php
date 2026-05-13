<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Services\DeveloperProgramPartnerShareService;

/**
 * Runs before merchant webhooks so payment.approved payloads include credited share amounts.
 */
class CreditDeveloperPartnerShareOnPaymentApproved
{
    public function __construct(
        protected DeveloperProgramPartnerShareService $partnerShareService
    ) {}

    public function handle(PaymentApproved $event): void
    {
        $this->partnerShareService->creditPartnerShareIfApplicable($event->payment);
    }
}
