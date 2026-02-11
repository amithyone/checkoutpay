<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Business;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class PaymentService
{
    public function __construct(
        protected AccountNumberService $accountNumberService,
        protected ChargeService $chargeService
    ) {}

    /**
     * Create a payment request.
     *
     * @param array $data amount, payer_name, webhook_url, service?, transaction_id?, business_website_id?, bank?, return_url?, website_url?
     * @param Business $business
     * @param Request|null $request
     * @param bool $useInvoicePool Use invoice pool for account assignment (e.g. invoice payments)
     * @return Payment
     */
    public function createPayment(array $data, Business $business, $request = null, bool $useInvoicePool = false): Payment
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount < 0.01) {
            throw new \InvalidArgumentException('Amount must be at least 0.01');
        }

        $transactionId = $data['transaction_id'] ?? null;
        if (!$transactionId) {
            do {
                $transactionId = 'TXN-' . strtoupper(Str::random(10));
            } while (Payment::where('transaction_id', $transactionId)->exists());
        }

        $account = $useInvoicePool
            ? $this->accountNumberService->assignInvoiceAccountNumber($business)
            : $this->accountNumberService->assignAccountNumber($business);

        if (!$account) {
            throw new \RuntimeException('No account number available. Please contact support.');
        }

        $website = null;
        if (!empty($data['business_website_id'])) {
            $website = $business->websites()->find($data['business_website_id']);
        }

        if ($useInvoicePool) {
            $charges = $this->chargeService->calculateInvoiceCharges($amount, $business);
            $chargePercentage = $charges['charge_percentage'] ?? 0;
            $chargeFixed = $charges['charge_fixed'] ?? 0;
            $totalCharges = $charges['total_charges'] ?? 0;
            $businessReceives = $charges['business_receives'] ?? $amount;
            $chargesPaidByCustomer = false;
        } else {
            $charges = $this->chargeService->calculateCharges($amount, $website, $business);
            $chargePercentage = $charges['charge_percentage'] ?? 0;
            $chargeFixed = $charges['charge_fixed'] ?? 0;
            $totalCharges = $charges['total_charges'] ?? 0;
            $businessReceives = $charges['business_receives'] ?? $amount;
            $chargesPaidByCustomer = $charges['paid_by_customer'] ?? false;
        }

        $payment = Payment::create([
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'payer_name' => $data['payer_name'] ?? null,
            'bank' => $data['bank'] ?? null,
            'webhook_url' => $data['webhook_url'] ?? $business->webhook_url ?? '',
            'account_number' => $account->account_number,
            'business_id' => $business->id,
            'business_website_id' => $data['business_website_id'] ?? null,
            'status' => Payment::STATUS_PENDING,
            'email_data' => array_filter([
                'service' => $data['service'] ?? null,
                'return_url' => $data['return_url'] ?? null,
                'website_url' => $data['website_url'] ?? null,
            ]),
            'charge_percentage' => $chargePercentage,
            'charge_fixed' => $chargeFixed,
            'total_charges' => $totalCharges,
            'business_receives' => $businessReceives,
            'charges_paid_by_customer' => $chargesPaidByCustomer,
        ]);

        return $payment;
    }
}
