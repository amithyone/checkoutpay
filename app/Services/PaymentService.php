<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * Create a new payment request
     */
    public function createPayment(array $data): Payment
    {
        // Generate transaction ID if not provided
        if (empty($data['transaction_id'])) {
            $data['transaction_id'] = $this->generateTransactionId();
        }

        // Normalize payer name
        if (!empty($data['payer_name'])) {
            $data['payer_name'] = strtolower(trim($data['payer_name']));
        }

        $payment = Payment::create([
            'transaction_id' => $data['transaction_id'],
            'amount' => $data['amount'],
            'payer_name' => $data['payer_name'] ?? null,
            'bank' => $data['bank'] ?? null,
            'webhook_url' => $data['webhook_url'],
            'status' => Payment::STATUS_PENDING,
        ]);

        \Log::info('Payment request created', [
            'transaction_id' => $payment->transaction_id,
            'amount' => $payment->amount,
            'payer_name' => $payment->payer_name,
        ]);

        return $payment;
    }

    /**
     * Generate a unique transaction ID
     */
    protected function generateTransactionId(): string
    {
        return 'TXN-' . now()->timestamp . '-' . Str::random(9);
    }
}
