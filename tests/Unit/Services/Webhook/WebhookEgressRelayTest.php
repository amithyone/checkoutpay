<?php

namespace Tests\Unit\Services\Webhook;

use App\Services\Webhook\WebhookEgressRelay;
use Tests\TestCase;

class WebhookEgressRelayTest extends TestCase
{
    public function test_sign_is_stable(): void
    {
        $sig = WebhookEgressRelay::sign('1700000000', 'nonce-1', '{"a":1}', 'secret');
        $this->assertSame(64, strlen($sig));
        $this->assertSame(
            WebhookEgressRelay::sign('1700000000', 'nonce-1', '{"a":1}', 'secret'),
            $sig
        );
    }

    public function test_client_disabled_without_secret(): void
    {
        config([
            'checkout.webhook_egress.relay_client_enabled' => true,
            'checkout.webhook_egress.relay_url' => 'https://check-outpay.com/api/v1/internal/webhook-egress',
            'checkout.webhook_egress.relay_secret' => '',
        ]);

        $this->assertFalse(WebhookEgressRelay::clientEnabled());
    }
}
