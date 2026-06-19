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
}
