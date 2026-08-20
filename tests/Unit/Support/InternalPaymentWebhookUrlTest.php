<?php

namespace Tests\Unit\Support;

use App\Support\InternalPaymentWebhookUrl;
use Tests\TestCase;

class InternalPaymentWebhookUrlTest extends TestCase
{
    public function test_invoice_webhook_is_internal(): void
    {
        $this->assertTrue(InternalPaymentWebhookUrl::isInternal('https://check-outpay.com/invoices/pay/ABC123/webhook'));
    }

    public function test_ticket_webhook_is_internal(): void
    {
        $this->assertTrue(InternalPaymentWebhookUrl::isInternal('https://example.com/tickets/payment/webhook/ORD-1'));
    }

    public function test_membership_webhook_is_internal(): void
    {
        $this->assertTrue(InternalPaymentWebhookUrl::isInternal('https://example.com/memberships/gold/payment/webhook'));
    }

    public function test_api_internal_path_is_internal(): void
    {
        $this->assertTrue(InternalPaymentWebhookUrl::isInternal('https://check-outpay.com/api/v1/internal/whatsapp-wallet-topup'));
    }

    public function test_merchant_webhook_is_not_internal(): void
    {
        $this->assertFalse(InternalPaymentWebhookUrl::isInternal('https://shop.example.com/checkout/webhook'));
    }

    public function test_empty_url_is_not_internal(): void
    {
        $this->assertFalse(InternalPaymentWebhookUrl::isInternal(''));
    }

    public function test_rewrite_legacy_internal_host_to_app_url(): void
    {
        config([
            'app.url' => 'https://check-outnow.com',
            'checkout.legacy_hosts' => ['check-outpay.com', 'www.check-outpay.com'],
        ]);

        $this->assertSame(
            'https://check-outnow.com/internal/whatsapp-wallet-topup',
            InternalPaymentWebhookUrl::rewriteToAppUrl('https://check-outpay.com/internal/whatsapp-wallet-topup')
        );
    }

    public function test_rewrite_leaves_merchant_webhooks_alone(): void
    {
        config(['app.url' => 'https://check-outnow.com']);

        $url = 'https://shop.example.com/hooks/checkout';
        $this->assertSame($url, InternalPaymentWebhookUrl::rewriteToAppUrl($url));
    }
}
