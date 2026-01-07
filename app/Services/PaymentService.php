<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Business;
use App\Services\AccountNumberService;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PaymentService
{
    public function __construct(
        protected AccountNumberService $accountNumberService
    ) {}

    /**
     * Create a new payment request
     */
    public function createPayment(array $data, ?Business $business = null): Payment
    {
        // Generate transaction ID if not provided
        if (empty($data['transaction_id'])) {
            $data['transaction_id'] = $this->generateTransactionId();
        }

        // Normalize payer name
        if (!empty($data['payer_name'])) {
            $data['payer_name'] = strtolower(trim($data['payer_name']));
        }

        // Set expiration time (default 24 hours, configurable)
        $expirationHours = config('payment.expiration_hours', 24);
        $expiresAt = now()->addHours($expirationHours);

        // Assign account number if not provided
        $accountNumber = null;
        if (empty($data['account_number'])) {
            $assignedAccount = $this->accountNumberService->assignAccountNumber($business);
            if ($assignedAccount) {
                $accountNumber = $assignedAccount->account_number;
                $assignedAccount->incrementUsage();
            }
        } else {
            $accountNumber = $data['account_number'];
        }

        $payment = Payment::create([
            'transaction_id' => $data['transaction_id'],
            'amount' => $data['amount'],
            'payer_name' => $data['payer_name'] ?? null,
            'bank' => $data['bank'] ?? null,
            'webhook_url' => $data['webhook_url'],
            'account_number' => $accountNumber,
            'business_id' => $business?->id,
            'status' => Payment::STATUS_PENDING,
            'expires_at' => $expiresAt,
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
