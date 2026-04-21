<?php

namespace App\Services;

use App\Models\AccountNumber;
use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\Payment;
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
     * @param array $data amount, payer_name, webhook_url?, service?, transaction_id?, business_website_id?, bank?, return_url?, website_url?
     *                       If webhook_url omitted/empty and business_website_id resolves to an approved website row
     *                       with webhook_url, that stored URL is copied onto the payment row.
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

        $website = null;
        if ($businessWebsiteId) {
            $website = $business->websites()->find($businessWebsiteId);
        }

        // Before assigning any account number (VA or pool), ensure explicit webhook matches saved URLs (website or business).
        $this->assertIncomingWebhookMatchesConfiguredWebsite($data, $business, $website);

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

        // Stored payment.webhook_url: explicit request wins, then website row for this business_website_id,
        // then business-level webhook (matches SendWebhookNotification URL priority for typical flows).
        $webhookUrlForPayment = '';
        $explicitWebhook = isset($data['webhook_url']) ? trim((string) $data['webhook_url']) : '';
        if ($explicitWebhook !== '') {
            $webhookUrlForPayment = $explicitWebhook;
        } elseif ($website && filled($website->webhook_url)) {
            $webhookUrlForPayment = $website->webhook_url;
        } elseif (! empty($business->webhook_url)) {
            $webhookUrlForPayment = (string) $business->webhook_url;
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
            'webhook_url' => $webhookUrlForPayment,
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

    /**
     * Non-empty webhook_url must match what is saved on the dashboard when we can determine it:
     * - explicit business_website_id → that approved site's webhook if set;
     * - else exactly one approved website has a webhook → compare to that one;
     * - else multiple approved sites have webhooks and no ID was sent → reject (ambiguous);
     * - else compare to business-level webhook_url when set.
     * Empty webhook_url skips here (filled from DB later in createPayment).
     */
    protected function assertIncomingWebhookMatchesConfiguredWebsite(array $data, Business $business, ?BusinessWebsite $websiteFromId): void
    {
        $explicit = isset($data['webhook_url']) ? trim((string) $data['webhook_url']) : '';
        if ($explicit === '') {
            return;
        }

        $approvedSitesWithWebhook = $business->approvedWebsites()
            ->whereNotNull('webhook_url')
            ->where('webhook_url', '!=', '')
            ->get();

        $providedWebsiteId = isset($data['business_website_id']) ? (int) $data['business_website_id'] : null;

        $websiteForComparison = null;

        if ($providedWebsiteId) {
            $selected = ($websiteFromId && (int) $websiteFromId->id === $providedWebsiteId)
                ? $websiteFromId
                : $business->websites()->find($providedWebsiteId);
            if ($selected && $selected->is_approved && filled($selected->webhook_url)) {
                $websiteForComparison = $selected;
            }
        } elseif ($approvedSitesWithWebhook->count() === 1) {
            $websiteForComparison = $approvedSitesWithWebhook->first();
        }

        if (! $websiteForComparison && $approvedSitesWithWebhook->count() > 1 && ! $providedWebsiteId) {
            throw new \InvalidArgumentException(
                'Provide business_website_id: multiple approved websites have webhook URLs configured.'
            );
        }

        if ($websiteForComparison && filled($websiteForComparison->webhook_url)) {
            $expected = $this->normalizeWebhookUrlForComparison((string) $websiteForComparison->webhook_url);
            $received = $this->normalizeWebhookUrlForComparison($explicit);
            if ($expected !== $received) {
                throw new \InvalidArgumentException(
                    'The webhook URL does not match the webhook URL configured for this website in your dashboard.'
                );
            }

            return;
        }

        if (filled($business->webhook_url)) {
            $expected = $this->normalizeWebhookUrlForComparison((string) $business->webhook_url);
            $received = $this->normalizeWebhookUrlForComparison($explicit);
            if ($expected !== $received) {
                throw new \InvalidArgumentException(
                    'The webhook URL does not match your business webhook URL in settings.'
                );
            }
        }
    }

    protected function normalizeWebhookUrlForComparison(string $url): string
    {
        return rtrim(trim($url), '/');
    }
}
