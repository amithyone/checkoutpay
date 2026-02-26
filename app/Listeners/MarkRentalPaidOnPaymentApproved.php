<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use Illuminate\Support\Facades\Log;

class MarkRentalPaidOnPaymentApproved
{
    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;

        if (!$payment->rental_id) {
            return;
        }

        $rental = $payment->rental;
        if (!$rental) {
            return;
        }

        if ($rental->status === 'active' || $rental->status === 'completed') {
            return;
        }

        try {
            $rental->update([
                'status' => \App\Models\Rental::STATUS_ACTIVE,
                'started_at' => $rental->started_at ?? now(),
            ]);
            Log::info('Rental marked active after payment approved', [
                'rental_id' => $rental->id,
                'rental_number' => $rental->rental_number,
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark rental active on payment approval', [
                'rental_id' => $rental->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
