<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\BusinessWebsite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationChargeSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_api_key(): void
    {
        $this->getJson('/api/v1/integration/charge-settings')
            ->assertUnauthorized();
    }

    /** @test */
    public function it_returns_charge_settings_for_matching_approved_website(): void
    {
        $business = Business::create([
            'name' => 'Store Co',
            'email' => 'store@example.com',
            'api_key' => 'pk_test_integration',
            'is_active' => true,
        ]);

        BusinessWebsite::create([
            'business_id' => $business->id,
            'website_url' => 'https://shop.example.com',
            'webhook_url' => 'https://shop.example.com/?wc-api=wc_checkoutpay_webhook',
            'is_approved' => true,
            'charge_percentage' => 1.5,
            'charge_fixed' => 75,
            'charges_paid_by_customer' => true,
            'charges_enabled' => true,
        ]);

        $response = $this->getJson('/api/v1/integration/charge-settings?website_url=https://shop.example.com&sample_amount=10000', [
            'X-API-Key' => $business->api_key,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.website.url', 'https://shop.example.com');
        $response->assertJsonPath('data.charge_percentage', 1.5);
        $response->assertJsonPath('data.charge_fixed', 75.0);
        $response->assertJsonPath('data.charges_paid_by_customer', true);
        $response->assertJsonPath('data.sample_amount', 10000);
        $response->assertJsonPath('data.sample.paid_by_customer', true);
        $this->assertGreaterThan(10000, (float) $response->json('data.sample.amount_to_pay'));
    }

    /** @test */
    public function it_returns_422_when_no_website_matches(): void
    {
        $business = Business::create([
            'name' => 'Other Co',
            'email' => 'other@example.com',
            'api_key' => 'pk_test_no_site',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/integration/charge-settings?website_url=https://unknown.example.com', [
            'X-API-Key' => $business->api_key,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
