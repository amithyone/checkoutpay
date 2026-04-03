<?php

namespace App\Services;

use App\Models\AccountNumber;
use App\Models\Payment;
use App\Models\Business;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PaymentService
{
    public function __construct(
        protected AccountNumberService $accountNumberService,
        protected ChargeService $chargeService,
        protected MevonPayVirtualAccountService $mevonPayVirtualAccountService
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

        $payerName = isset($data['payer_name']) ? trim((string) $data['payer_name']) : null;
        $businessWebsiteId = isset($data['business_website_id']) ? (int) $data['business_website_id'] : null;
        $requestedService = (string) ($data['service'] ?? ($useInvoicePool ? 'invoice' : 'general'));

        $externalOverride = $data['external_override'] ?? null;
        if (is_string($externalOverride) && in_array($externalOverride, ['external_only', 'hybrid', 'internal_only'], true)) {
            $mode = $externalOverride;
        } else {
            $mode = $business->externalProviderModeForService('mevonpay', $requestedService);
        }

        $forceExternal = $mode === 'external_only';
        $preferExternal = $mode === 'hybrid';

        $account = null;
        $externalExpiresAt = null;

        if ($forceExternal || $preferExternal) {
            $vaMode = $business->externalProviderVaGenerationMode('mevonpay');

            try {
                if ($vaMode === 'temp') {
                    $registrationNumber = trim((string) ($data['registration_number'] ?? config('services.mevonpay.temp_va_registration_number', '')));
                    $bvn = $data['bvn'] ?? null;
                    if ($registrationNumber === '' && empty($bvn)) {
                        throw new \RuntimeException('registration_number (preferred) or bvn is required for Temporary VA (createtempva.php).');
                    }

                    $fname = $data['fname'] ?? null;
                    $lname = $data['lname'] ?? null;

                    if (empty($fname) || empty($lname)) {
                        // Best-effort split: "First Last" from payer_name.
                        $nameParts = preg_split('/\s+/', (string) ($payerName ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                        if (count($nameParts) >= 2) {
                            $fname = $nameParts[0] ?? null;
                            $lname = trim(implode(' ', array_slice($nameParts, 1)));
                        }
                    }

                    if (empty($fname)) {
                        $fname = 'Checkout';
                    }
                    if (empty($lname)) {
                        $lname = 'Pay';
                    }

                    $va = $this->mevonPayVirtualAccountService->createTempVa(
                        (string) $fname,
                        (string) $lname,
                        $registrationNumber !== '' ? $registrationNumber : null,
                        ! empty($bvn) ? (string) $bvn : null
                    );
                } else {
                    // Default to dynamic VA; it doesn't require BVN.
                    $va = $this->mevonPayVirtualAccountService->createDynamicVa(
                        $amount,
                        'NGN'
                    );
                }

                $account = AccountNumber::updateOrCreate(
                    ['account_number' => $va['account_number']],
                    [
                        'account_name' => $va['account_name'] ?? ($payerName ?: ''),
                        'bank_name' => $va['bank_name'] ?? '',
                        'business_id' => $business->id,
                        'business_website_id' => $businessWebsiteId,
                        'is_pool' => false,
                        'is_external' => true,
                        'external_provider' => 'mevonpay',
                        'is_active' => true,
                    ]
                );

                if (!empty($va['expires_on'])) {
                    try {
                        $externalExpiresAt = Carbon::parse((string) $va['expires_on']);
                    } catch (\Throwable) {
                        $externalExpiresAt = null;
                    }
                }
            } catch (\Throwable $e) {
                if ($preferExternal) {
                    // In hybrid mode, fall back to internal pools if external VA creation fails.
                    $account = null;
                    $externalExpiresAt = null;
                } else {
                    throw $e;
                }
            }
        }

        if (!$account) {
            if ($useInvoicePool && !$forceExternal && !$preferExternal) {
                $account = $this->accountNumberService->assignInvoiceAccountNumber($business);
            } else {
                $account = $this->accountNumberService->assignAccountNumber(
                    $business,
                    $payerName ?: null,
                    $businessWebsiteId ?: null,
                    [
                        // In this branch we already decided external VA creation didn't happen,
                        // so do not force any external accounts here.
                        'force_external' => false,
                        'prefer_external' => false,
                    ]
                );
            }
        }

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

        $isExternalAssigned = (bool) ($account->is_external ?? false);

        $payment = Payment::create([
            'payment_source' => $isExternalAssigned
                ? Payment::SOURCE_EXTERNAL_MEVONPAY
                : Payment::SOURCE_INTERNAL,
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
                'skip_auto_match' => $isExternalAssigned ? true : null,
            ]),
            'charge_percentage' => $chargePercentage,
            'charge_fixed' => $chargeFixed,
            'total_charges' => $totalCharges,
            'business_receives' => $businessReceives,
            'charges_paid_by_customer' => $chargesPaidByCustomer,
        ]);

        if ($isExternalAssigned && $externalExpiresAt) {
            $payment->expires_at = $externalExpiresAt;
            $payment->save();
        }

        return $payment;
    }
}
