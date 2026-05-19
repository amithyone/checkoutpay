<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\ChargeService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected ChargeService $chargeService,
    ) {}

    /**
     * Charge settings for an integration (e.g. WooCommerce plugin), mirroring dashboard website rules.
     */
    public function chargeSettings(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->user();

        $websiteUrl = $request->query('website_url');
        $webhookUrl = $request->query('webhook_url');
        $sampleAmount = max(0.01, (float) $request->query('sample_amount', 10000));

        $website = $this->paymentService->resolveWebsiteForIntegration(
            $business,
            is_string($websiteUrl) ? $websiteUrl : null,
            is_string($webhookUrl) ? $webhookUrl : null,
        );

        if (! $website) {
            return response()->json([
                'success' => false,
                'message' => 'No approved CheckoutPay business website matches this store URL. Add and approve your WooCommerce site in the CheckoutPay dashboard, and ensure the website URL matches.',
            ], 422);
        }

        $chargesEnabled = $this->chargeService->areChargesEnabled($website, $business);
        $chargeExempt = $this->chargeService->isChargeExempt($website, $business);
        $chargePercentage = $this->chargeService->getChargePercentage($website, $business);
        $chargeFixed = $this->chargeService->getChargeFixed($website, $business);
        $paidByCustomer = $this->chargeService->isPaidByCustomer($website, $business);

        $sample = $this->chargeService->calculateCharges($sampleAmount, $website, $business);

        $paidByLabel = $paidByCustomer
            ? 'Customer (added to amount they transfer)'
            : 'Merchant (deducted from settlement)';

        if (! $chargesEnabled || $chargeExempt) {
            $paidByLabel = 'No charges apply';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'website' => [
                    'id' => $website->id,
                    'url' => $website->website_url,
                    'webhook_url' => $website->webhook_url,
                ],
                'charges_enabled' => $chargesEnabled,
                'charge_exempt' => $chargeExempt,
                'charge_percentage' => (float) $chargePercentage,
                'charge_fixed' => (float) $chargeFixed,
                'charges_paid_by_customer' => $paidByCustomer,
                'paid_by_label' => $paidByLabel,
                'sample_amount' => $sampleAmount,
                'sample' => [
                    'original_amount' => (float) ($sample['original_amount'] ?? $sampleAmount),
                    'total_charges' => (float) ($sample['total_charges'] ?? 0),
                    'amount_to_pay' => (float) ($sample['amount_to_pay'] ?? $sampleAmount),
                    'business_receives' => (float) ($sample['business_receives'] ?? $sampleAmount),
                    'paid_by_customer' => (bool) ($sample['paid_by_customer'] ?? false),
                    'exempt' => (bool) ($sample['exempt'] ?? false),
                ],
                'dashboard_note' => 'Change fees, who pays charges, and split payment options in your CheckoutPay business website settings.',
                'portal_url' => $this->checkoutPayPortalUrl(),
                'dashboard_websites_url' => $this->checkoutPayDashboardWebsitesUrl(),
            ],
        ]);
    }

    protected function checkoutPayPortalUrl(): string
    {
        $configured = rtrim((string) config('app.url'), '/');
        if ($configured !== '' && ! str_contains($configured, 'localhost') && ! str_contains($configured, '127.0.0.1')) {
            return $configured;
        }

        return 'https://check-outpay.com';
    }

    protected function checkoutPayDashboardWebsitesUrl(): string
    {
        try {
            return route('business.websites.index', [], true);
        } catch (\Throwable) {
            return $this->checkoutPayPortalUrl().'/dashboard/websites';
        }
    }
}
