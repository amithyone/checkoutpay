<?php

namespace Tests\Unit\Consumer;

use App\Services\Consumer\VirtualCardUserFacingMessage;
use Tests\TestCase;

class VirtualCardUserFacingMessageTest extends TestCase
{
    public function test_hides_merchant_usd_errors(): void
    {
        $this->assertTrue(VirtualCardUserFacingMessage::isInternalOperationalError('Insufficient USD balance'));
        $this->assertTrue(VirtualCardUserFacingMessage::isInternalOperationalError('MevonPay NGN balance is too low to auto-buy USD'));
    }

    public function test_sanitize_replaces_internal_errors_with_fallback(): void
    {
        $fallback = VirtualCardUserFacingMessage::topupFailedRefunded();

        $this->assertSame(
            $fallback,
            VirtualCardUserFacingMessage::sanitizeProviderMessage('Insufficient USD balance', $fallback),
        );
    }

    public function test_sanitize_keeps_safe_provider_errors(): void
    {
        $this->assertSame(
            'Card is not active.',
            VirtualCardUserFacingMessage::sanitizeProviderMessage('Card is not active.', VirtualCardUserFacingMessage::topupFailedRefunded()),
        );
    }
}
