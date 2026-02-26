<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\Payment;
use Illuminate\Support\Str;

class RentalPaymentService
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Create a payment for an approved rental. Renter pays via the payment page.
     */
    public function createPaymentForRental(Rental $rental): Payment
    {
        if ($rental->payment_id) {
            return $rental->payment;
        }

        $business = $rental->business;
        $amount = (float) $rental->total_amount;

        $payment = $this->paymentService->createPayment([
            'amount' => $amount,
            'payer_name' => $rental->renter_name,
            'webhook_url' => $business->webhook_url ?? '',
            'service' => 'rental',
            'business_website_id' => null,
        ], $business, request(), false);

        $payment->update([
            'rental_id' => $rental->id,
            'expires_at' => now()->addHours(48),
        ]);

        $emailData = $payment->email_data ?? [];
        $emailData['rental_id'] = $rental->id;
        $emailData['rental_number'] = $rental->rental_number;
        $payment->update(['email_data' => $emailData]);

        $code = Str::random(32);
        while (Rental::where('payment_link_code', $code)->exists()) {
            $code = Str::random(32);
        }

        $rental->update([
            'payment_id' => $payment->id,
            'payment_link_code' => $code,
        ]);

        return $payment->fresh();
    }
}
