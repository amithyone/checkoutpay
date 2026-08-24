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
            'checkout.legacy_host_redirect_skip_prefixes' => ['/api/', '/cron/', '/enter0'],
        ]);

        $this->get('https://check-outpay.com/dashboard/login?x=1')
            ->assertRedirect('https://check-outnow.com/dashboard/login?x=1')
            ->assertStatus(301);

        $admin = $this->get('https://check-outpay.com/enter0/login');
        $this->assertNotEquals(301, $admin->status());
        $this->assertNotEquals(308, $admin->status());
    }

    public function test_api_paths_are_not_redirected_so_namecheap_can_relay(): void
    {
        config([
            'checkout.legacy_host_redirect_enabled' => true,
            'checkout.legacy_host_redirect_force_in_tests' => true,
            'checkout.legacy_host_redirect_to' => 'https://check-outnow.com',
            'checkout.legacy_hosts' => ['check-outpay.com'],
            'checkout.legacy_host_redirect_skip_prefixes' => ['/api/', '/cron/', '/internal/'],
        ]);

        // Must not 301/308 away — Namecheap keeps /api for egress relay during cutover.
        $response = $this->post('https://check-outpay.com/api/v1/internal/webhook-egress');
        $this->assertNotEquals(301, $response->status());
        $this->assertNotEquals(308, $response->status());
    }

    public function test_check_outnow_is_not_redirected(): void
    {
        config([
            'checkout.legacy_host_redirect_enabled' => true,
            'checkout.legacy_host_redirect_force_in_tests' => true,
        ]);

        $response = $this->get('https://check-outnow.com/quarantine/status');
        $this->assertNotEquals(301, $response->status());
        $this->assertNotEquals(308, $response->status());
    }

    public function test_investor_paths_are_not_redirected(): void
    {
        config([
            'checkout.legacy_host_redirect_enabled' => true,
            'checkout.legacy_host_redirect_force_in_tests' => true,
            'checkout.legacy_host_redirect_to' => 'https://check-outnow.com',
            'checkout.legacy_hosts' => ['check-outpay.com'],
            'checkout.legacy_host_redirect_skip_prefixes' => ['/api/', '/cron/', '/enter0', '/investor'],
        ]);

        $gate = $this->get('https://check-outpay.com/investor/access/abc123token');
        $this->assertNotEquals(301, $gate->status());
        $this->assertNotEquals(308, $gate->status());

        $lookup = $this->get('https://check-outpay.com/investor/access');
        $this->assertNotEquals(301, $lookup->status());
        $this->assertNotEquals(308, $lookup->status());
    }
}
