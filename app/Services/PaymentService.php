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

        // If caller omitted business_website_id but sent website/webhook hints, bind to the matching approved website.
        // This ensures website-level charges are applied even when the account number is business-level.
        if (! $website) {
            $website = $this->resolveWebsiteFromIncomingData($business, $data);
            if ($website) {
                $businessWebsiteId = (int) $website->id;
            }
        }

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
            'business_website_id' => $businessWebsiteId,
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
     * - business_website_id → compare only to that approved site's webhook (when set);
     * - else same URL as one of the approved sites' saved webhooks → accept (disambiguates multiple sites);
     * - else exactly one approved website has a webhook → compare to that one;
     * - else compare to business-level webhook_url when set;
     * - else explicit URL does not match any saved site webhook → error.
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
        } else {
            foreach ($approvedSitesWithWebhook as $site) {
                if (
                    $this->normalizeWebhookUrlForComparison((string) $site->webhook_url)
                    === $this->normalizeWebhookUrlForComparison($explicit)
                ) {
                    $websiteForComparison = $site;
                    break;
                }
            }
            if (! $websiteForComparison && $approvedSitesWithWebhook->count() === 1) {
                $websiteForComparison = $approvedSitesWithWebhook->first();
            }
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

            return;
        }

        if ($approvedSitesWithWebhook->isNotEmpty()) {
            throw new \InvalidArgumentException(
                'The webhook URL does not match any webhook URL saved on your approved websites. Fix webhook_url or send business_website_id.'
            );
        }
    }

    protected function normalizeWebhookUrlForComparison(string $url): string
    {
        return rtrim(trim($url), '/');
    }

    /**
     * Best-effort website resolver for API callers that do not pass business_website_id.
     * Priority:
     * 1) Exact webhook_url match with approved website webhook_url
     * 2) website_url host/domain match with approved website website_url
     */
    protected function resolveWebsiteFromIncomingData(Business $business, array $data): ?BusinessWebsite
    {
        $approvedWebsites = $business->approvedWebsites()->get();
        if ($approvedWebsites->isEmpty()) {
            return null;
        }

        $incomingWebhook = isset($data['webhook_url']) ? trim((string) $data['webhook_url']) : '';
        if ($incomingWebhook !== '') {
            $incomingWebhookNormalized = $this->normalizeWebhookUrlForComparison($incomingWebhook);
            foreach ($approvedWebsites as $site) {
                $siteWebhook = trim((string) ($site->webhook_url ?? ''));
                if ($siteWebhook === '') {
                    continue;
                }
                if ($this->normalizeWebhookUrlForComparison($siteWebhook) === $incomingWebhookNormalized) {
                    return $site;
                }
            }
        }

        $incomingWebsiteUrl = isset($data['website_url']) ? trim((string) $data['website_url']) : '';
        if ($incomingWebsiteUrl === '') {
            return null;
        }

        $incomingHost = $this->normalizeHost($incomingWebsiteUrl);
        if (! $incomingHost) {
            return null;
        }

        foreach ($approvedWebsites as $site) {
            $siteHost = $this->normalizeHost((string) ($site->website_url ?? ''));
            if (! $siteHost) {
                continue;
            }
            if ($incomingHost === $siteHost || str_ends_with($incomingHost, '.'.$siteHost)) {
                return $site;
            }
        }

        return null;
    }

    protected function normalizeHost(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return null;
        }

        return strtolower(preg_replace('/^www\./', '', $host));
    }
}
