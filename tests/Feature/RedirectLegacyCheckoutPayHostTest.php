<?php

namespace Tests\Feature;

use Tests\TestCase;

class RedirectLegacyCheckoutPayHostTest extends TestCase
{
    public function test_check_outpay_get_redirects_to_check_outnow(): void
    {
        config([
            'checkout.legacy_host_redirect_enabled' => true,
            'checkout.legacy_host_redirect_force_in_tests' => true,
            'checkout.legacy_host_redirect_to' => 'https://check-outnow.com',
            'checkout.legacy_hosts' => ['check-outpay.com', 'www.check-outpay.com'],
        ]);

        $this->get('https://check-outpay.com/dashboard/login?x=1')
            ->assertRedirect('https://check-outnow.com/dashboard/login?x=1')
            ->assertStatus(301);
    }

    public function test_api_post_uses_permanent_redirect_308(): void
    {
        config([
            'checkout.legacy_host_redirect_enabled' => true,
            'checkout.legacy_host_redirect_force_in_tests' => true,
            'checkout.legacy_host_redirect_to' => 'https://check-outnow.com',
            'checkout.legacy_hosts' => ['check-outpay.com'],
        ]);

        $this->post('https://check-outpay.com/api/v1/payment-request')
            ->assertStatus(308)
            ->assertHeader('Location', 'https://check-outnow.com/api/v1/payment-request');
    }

    public function test_check_outnow_is_not_redirected(): void
    {
        config([
            'checkout.legacy_host_redirect_enabled' => true,
            'checkout.legacy_host_redirect_force_in_tests' => true,
        ]);

        // Quarantine/home may 200 or other — just must not 301 to itself
        $response = $this->get('https://check-outnow.com/quarantine/status');
        $this->assertNotEquals(301, $response->status());
        $this->assertNotEquals(308, $response->status());
    }
}
