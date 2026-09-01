<?php

namespace Tests\Feature;

use Tests\TestCase;

class RedirectLegacyCheckoutPayHostTest extends TestCase
{
    public function test_legacy_redirect_is_off_by_default(): void
    {
        config([
            'checkout.legacy_host_redirect_enabled' => false,
            'checkout.legacy_host_redirect_force_in_tests' => true,
            'checkout.legacy_hosts' => ['check-outpay.com'],
        ]);

        $dashboard = $this->get('https://check-outpay.com/dashboard/login?x=1');
        $this->assertNotEquals(301, $dashboard->status());
        $this->assertNotEquals(308, $dashboard->status());

        $investor = $this->get('https://check-outpay.com/investor/access/abc123token');
        $this->assertNotEquals(301, $investor->status());
        $this->assertNotEquals(308, $investor->status());
    }

    public function test_check_outnow_is_not_redirected(): void
    {
        config([
            'checkout.legacy_host_redirect_enabled' => false,
            'checkout.legacy_host_redirect_force_in_tests' => true,
        ]);

        $response = $this->get('https://check-outnow.com/quarantine/status');
        $this->assertNotEquals(301, $response->status());
        $this->assertNotEquals(308, $response->status());
    }
}
