<?php

namespace App\Services\Business;

use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\Payment;
use App\Support\WebsiteUrl;
use Illuminate\Support\Collection;

final class PaymentWebsiteAttributionService
{
    public function resolveWebsite(Payment $payment): ?BusinessWebsite
    {
        if ($payment->business_website_id) {
            return $payment->relationLoaded('website')
                ? $payment->website
                : BusinessWebsite::query()->find($payment->business_website_id);
        }

        if (! $payment->business_id) {
            return null;
        }

        $business = $payment->relationLoaded('business')
            ? $payment->business
            : Business::query()->find($payment->business_id);

        if (! $business) {
            return null;
        }

        return $this->matchForBusiness($business, $payment);
    }

    public function resolveWebsiteId(Payment $payment): ?int
    {
        return $this->resolveWebsite($payment)?->id;
    }

    public function paymentMatchesWebsite(Payment $payment, BusinessWebsite $website): bool
    {
        if ((int) $payment->business_website_id === (int) $website->id) {
            return true;
        }

        if ($payment->business_website_id !== null) {
            return false;
        }

        return $this->matchForBusinessWebsite($website, $payment) !== null;
    }

    /**
     * Persist business_website_id when it can be inferred safely.
     */
    public function attributePayment(Payment $payment): bool
    {
        if ($payment->business_website_id || ! $payment->business_id) {
            return false;
        }

        $website = $this->resolveWebsite($payment);
        if (! $website) {
            return false;
        }

        return $payment->update(['business_website_id' => $website->id]);
    }

    /**
     * @return Collection<int, Payment>
     */
    public function inferUnattributedPaymentsForWebsite(Business $business, BusinessWebsite $website): Collection
    {
        return Payment::query()
            ->where('business_id', $business->id)
            ->whereNull('business_website_id')
            ->get()
            ->filter(fn (Payment $payment) => $this->paymentMatchesWebsite($payment, $website))
            ->values();
    }

    private function matchForBusiness(Business $business, Payment $payment): ?BusinessWebsite
    {
        $websites = $business->relationLoaded('websites')
            ? $business->websites->where('is_approved', true)
            : $business->approvedWebsites()->get();

        foreach ($websites as $website) {
            if ($this->matchForBusinessWebsite($website, $payment) !== null) {
                return $website;
            }
        }

        return null;
    }

    private function matchForBusinessWebsite(BusinessWebsite $website, Payment $payment): ?BusinessWebsite
    {
        $incomingWebhook = trim((string) ($payment->webhook_url ?? ''));
        if ($incomingWebhook !== '') {
            $siteWebhook = trim((string) ($website->webhook_url ?? ''));
            if ($siteWebhook !== '' && $this->normalizeWebhook($siteWebhook) === $this->normalizeWebhook($incomingWebhook)) {
                return $website;
            }

            foreach (WebsiteUrl::hostsForMatching($website->website_url, $website->webhook_url) as $host) {
                if (str_contains(strtolower($incomingWebhook), $host)) {
                    return $website;
                }
            }
        }

        $incomingWebsiteUrl = trim((string) ($emailData['website_url'] ?? $emailData['return_url'] ?? ''));

        if ($incomingWebsiteUrl !== '' && WebsiteUrl::hostsMatch($incomingWebsiteUrl, $website->website_url)) {
            return $website;
        }

        return null;
    }

    private function normalizeWebhook(string $url): string
    {
        return rtrim(trim($url), '/');
    }
}
