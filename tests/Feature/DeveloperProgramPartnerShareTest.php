<?php

namespace Tests\Feature;

use App\Events\PaymentApproved;
use App\Listeners\CreditDeveloperPartnerShareOnPaymentApproved;
use App\Models\Business;
use App\Models\DeveloperProgramApplication;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\TransactionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperProgramPartnerShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_credits_partner_balance_once_and_second_invocation_is_idempotent(): void
    {
        Setting::set('developer_program_fee_share_percent', 25, 'float', 'developer_program', 'test');

        $merchant = Business::create([
            'name' => 'Merchant',
            'email' => 'merchant_' . uniqid('', true) . '@test.com',
            'api_key' => 'pk_m_' . uniqid('', true),
            'is_active' => true,
        ]);

        $partner = Business::create([
            'name' => 'Partner Dev',
            'email' => 'partner_' . uniqid('', true) . '@test.com',
            'api_key' => 'pk_p_' . uniqid('', true),
            'is_active' => true,
        ]);

        DeveloperProgramApplication::create([
            'name' => 'Partner',
            'business_id' => (string) $partner->id,
            'phone' => '080',
            'email' => 'devapp_' . uniqid('', true) . '@test.com',
            'whatsapp' => '080',
            'community_preference' => 'slack',
            'status' => DeveloperProgramApplication::STATUS_APPROVED,
            'partner_fee_share_percent' => null,
        ]);

        $payment = Payment::create([
            'transaction_id' => 'TXN-TEST-' . uniqid('', true),
            'amount' => 1000.00,
            'payer_name' => 'Buyer',
            'webhook_url' => 'https://example.com/hook',
            'business_id' => $merchant->id,
            'developer_program_partner_business_id' => $partner->id,
            'status' => Payment::STATUS_APPROVED,
            'account_number' => '0123456789',
            'total_charges' => 200.00,
            'business_receives' => 800.00,
            'charge_fixed' => 0,
            'charge_percentage' => 0,
        ]);

        $listener = app(CreditDeveloperPartnerShareOnPaymentApproved::class);
        $listener->handle(new PaymentApproved($payment));

        $payment->refresh();
        $partner->refresh();

        $this->assertSame(50.0, (float) $payment->developer_program_partner_share_amount);
        $this->assertNotNull($payment->developer_program_partner_share_credited_at);
        $this->assertSame(50.0, (float) $partner->balance);

        $this->assertTrue(
            TransactionLog::query()
                ->where('payment_id', $payment->id)
                ->where('event_type', TransactionLog::EVENT_DEVELOPER_PROGRAM_PARTNER_SHARE_CREDITED)
                ->exists()
        );

        $listener->handle(new PaymentApproved($payment->fresh()));
        $partner->refresh();
        $this->assertSame(50.0, (float) $partner->balance);

        $this->assertSame(
            1,
            TransactionLog::query()
                ->where('payment_id', $payment->id)
                ->where('event_type', TransactionLog::EVENT_DEVELOPER_PROGRAM_PARTNER_SHARE_CREDITED)
                ->count()
        );
    }
}
