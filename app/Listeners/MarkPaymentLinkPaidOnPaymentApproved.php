<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Models\PaymentLink;
use App\Models\PaymentLinkPayment;
use App\Services\PaymentLinkService;
use Illuminate\Support\Facades\Log;

class MarkPaymentLinkPaidOnPaymentApproved
{
    public function __construct(
        protected PaymentLinkService $links
    ) {}

    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;

        $pivot = PaymentLinkPayment::query()->where('payment_id', $payment->id)->first();
        $link = $pivot?->paymentLink;

        if (! $link) {
            $linkId = (int) (($payment->email_data['payment_link_id'] ?? 0));
            if ($linkId > 0) {
                $link = PaymentLink::query()->find($linkId);
            }
        }

        if (! $link) {
            return;
        }

        try {
            $this->links->recordApproved($link, $payment);
        } catch (\Throwable $e) {
            Log::error('payment_link.approve_failed', [
                'payment_link_id' => $link->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
