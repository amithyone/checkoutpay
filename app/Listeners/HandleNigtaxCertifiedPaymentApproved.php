<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Services\NigtaxCertifiedReportService;

class HandleNigtaxCertifiedPaymentApproved
{
    public function __construct(
        protected NigtaxCertifiedReportService $nigtaxCertifiedReportService
    ) {}

    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;
        $emailData = $payment->email_data ?? [];
        if (empty($emailData['nigtax_certified_order_id'])) {
            return;
        }

        if ($payment->status !== \App\Models\Payment::STATUS_APPROVED) {
            return;
        }

        $this->nigtaxCertifiedReportService->markPaidFromPayment($payment);
    }
}
