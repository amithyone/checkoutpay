<?php

namespace Tests\Unit\Payout;

use App\Models\Business;
use App\Services\Payout\BankPayoutNarration;
use Tests\TestCase;

class BankPayoutNarrationTest extends TestCase
{
    public function test_for_whatsapp_returns_fixed_string(): void
    {
        $this->assertSame('WhatsApp wallet bank transfer', BankPayoutNarration::forWhatsapp());
    }

    public function test_for_consumer_app_empty_remark_uses_default(): void
    {
        $this->assertSame('Checkout App', BankPayoutNarration::forConsumerApp(null));
        $this->assertSame('Checkout App', BankPayoutNarration::forConsumerApp('   '));
    }

    public function test_for_consumer_app_uses_trimmed_remark(): void
    {
        $this->assertSame('Rent payment', BankPayoutNarration::forConsumerApp('  Rent payment  '));
    }

    public function test_for_business_withdrawal_uses_custom_narration(): void
    {
        $business = new Business(['name' => 'Acme Ltd']);
        $this->assertSame('Supplier pay', BankPayoutNarration::forBusinessWithdrawal($business, 'Supplier pay'));
    }

    public function test_for_business_withdrawal_empty_uses_business_name(): void
    {
        $business = new Business(['name' => 'Acme Ltd']);
        $this->assertSame('Acme Ltd', BankPayoutNarration::forBusinessWithdrawal($business, null));
    }

    public function test_for_business_withdrawal_empty_name_uses_fallback(): void
    {
        $business = new Business(['name' => '']);
        $this->assertSame('Business withdrawal', BankPayoutNarration::forBusinessWithdrawal($business, null));
    }
}
