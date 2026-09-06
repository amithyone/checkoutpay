<?php

namespace Tests\Feature\Business;

use App\Listeners\MarkPaymentLinkPaidOnPaymentApproved;
use App\Events\PaymentApproved;
use App\Models\AccountNumber;
use App\Models\Admin;
use App\Models\Business;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Services\MevonPayVirtualAccountService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class PaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    private function business(array $overrides = []): Business
    {
        return Business::create(array_merge([
            'name' => 'Link Shop',
            'email' => 'linkshop-'.uniqid().'@test.com',
            'is_active' => true,
            'uses_external_account_numbers' => false,
        ], $overrides));
    }

    private function invoicePoolAccount(): AccountNumber
    {
        return AccountNumber::create([
            'account_number' => '5555555555',
            'account_name' => 'Invoice Pool',
            'bank_name' => 'Test Bank',
            'is_invoice_pool' => true,
            'is_active' => true,
        ]);
    }

    /**
     * @param  callable(string, string): void|null  $assertRc
     */
    private function mockTempVa(?callable $assertRc = null, string $accountNumber = '9998887777'): void
    {
        config(['services.mevonpay.temp_va_registration_number' => 'RC0001111']);

        $mock = Mockery::mock(MevonPayVirtualAccountService::class);
        $expectation = $mock->shouldReceive('createTempVa')->once();
        if ($assertRc) {
            $expectation->withArgs(function (string $rc, string $type) use ($assertRc) {
                $assertRc($rc, $type);

                return true;
            });
        }
        $expectation->andReturn([
            'account_number' => $accountNumber,
            'account_name' => 'TEMP VA',
            'bank_name' => 'Rubies',
            'bank_code' => '090175',
            'expires_on' => now()->addHours(24)->toIso8601String(),
        ]);
        $this->app->instance(MevonPayVirtualAccountService::class, $mock);
        $this->app->forgetInstance(PaymentService::class);
    }

    private function mockCardCheckout(string $url = 'https://checkout.paga.test/pay/link'): void
    {
        config([
            'services.mevonpay.base_url' => 'https://mevonpay.test',
            'services.mevonpay.secret_key' => 'test-secret-key',
            'services.mevonpay.card_checkout_path' => '/V1/card_checkout',
            'services.mevonpay.card_checkout_auth' => 'raw',
        ]);

        Http::fake([
            'mevonpay.test/*' => Http::response([
                'status' => true,
                'message' => 'OK',
                'data' => [
                    'checkout_url' => $url,
                    'payment_reference' => 'PAY_LINK_1',
                ],
            ], 200),
        ]);
    }

    public function test_create_page_shows_live_customer_preview(): void
    {
        $this->actingAs($this->business(), 'business')
            ->get('/dashboard/payment-links/create')
            ->assertOk()
            ->assertSee('Customer preview')
            ->assertSee('Pay')
            ->assertSee('Link Shop')
            ->assertSee('Payment instructions');
    }

    public function test_business_can_create_fixed_one_time_and_open_reusable_links(): void
    {
        $business = $this->business();
        $this->actingAs($business, 'business');

        $this->post('/dashboard/payment-links', [
            'title' => 'School fees',
            'description' => 'Term 1',
            'amount_mode' => 'fixed',
            'amount' => 25000,
            'reuse_mode' => 'one_time',
        ])->assertRedirect();

        $this->assertDatabaseHas('payment_links', [
            'business_id' => $business->id,
            'title' => 'School fees',
            'reuse_mode' => 'one_time',
            'status' => 'active',
        ]);
        $this->assertEquals(25000.00, (float) PaymentLink::where('title', 'School fees')->value('amount'));

        $this->post('/dashboard/payment-links', [
            'title' => 'Tip jar',
            'amount_mode' => 'open',
            'reuse_mode' => 'reusable',
        ])->assertRedirect();

        $open = PaymentLink::where('title', 'Tip jar')->first();
        $this->assertNotNull($open);
        $this->assertNull($open->amount);
        $this->assertSame('reusable', $open->reuse_mode);
    }

    public function test_public_pay_creates_temp_va_with_checkout_rc_when_business_has_no_cac(): void
    {
        $pool = $this->invoicePoolAccount();
        $this->mockTempVa(function (string $rc, string $type) {
            $this->assertSame('RC0001111', $rc);
            $this->assertSame('RC', $type);
        });
        $business = $this->business();
        $link = PaymentLink::create([
            'business_id' => $business->id,
            'title' => 'Deposit',
            'amount' => 1500,
            'currency' => 'NGN',
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);

        $this->get('/pay/l/'.$link->code)
            ->assertOk()
            ->assertSee('Deposit')
            ->assertSee('Waiting for payment')
            ->assertSee('Check payment status')
            ->assertSee('Create your own payment link');

        $this->assertDatabaseHas('payments', [
            'business_id' => $business->id,
        ]);
        $payment = Payment::where('business_id', $business->id)->first();
        $this->assertSame('payment_link', $payment->email_data['service'] ?? null);
        $this->assertSame($link->id, (int) ($payment->email_data['payment_link_id'] ?? 0));
        $this->assertSame('platform', $payment->email_data['temp_va_rc_source'] ?? null);
        $this->assertSame('9998887777', $payment->account_number);
        $this->assertNotSame($pool->account_number, $payment->account_number);
        $this->assertNotNull($payment->expires_at);
        $this->assertDatabaseHas('payment_link_payments', [
            'payment_link_id' => $link->id,
            'payment_id' => $payment->id,
        ]);
    }

    public function test_public_pay_uses_business_cac_for_temp_va_when_present(): void
    {
        $this->invoicePoolAccount();
        $this->mockTempVa(function (string $rc, string $type) {
            $this->assertSame('RC888777', $rc);
            $this->assertSame('RC', $type);
        }, '1112223334');
        $business = $this->business([
            'cac_registration_number' => 'RC-888777',
        ]);
        $link = PaymentLink::create([
            'business_id' => $business->id,
            'title' => 'With CAC',
            'amount' => 2200,
            'currency' => 'NGN',
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);

        $this->get('/pay/l/'.$link->code)->assertOk()->assertSee('With CAC');

        $payment = Payment::where('business_id', $business->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('1112223334', $payment->account_number);
        $this->assertSame('business_cac', $payment->email_data['temp_va_rc_source'] ?? null);
        $this->assertNotSame('5555555555', $payment->account_number);
    }

    public function test_one_time_link_cannot_collect_after_approved(): void
    {
        $this->mockTempVa();
        $business = $this->business();
        $link = PaymentLink::create([
            'business_id' => $business->id,
            'title' => 'Once only',
            'amount' => 800,
            'currency' => 'NGN',
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);

        $this->get('/pay/l/'.$link->code)->assertOk();
        $payment = Payment::where('business_id', $business->id)->first();
        $this->assertNotNull($payment);

        $payment->update(['status' => Payment::STATUS_APPROVED]);
        app(MarkPaymentLinkPaidOnPaymentApproved::class)->handle(new PaymentApproved($payment->fresh()));

        $link->refresh();
        $this->assertSame(PaymentLink::STATUS_PAID, $link->status);
        $this->assertSame(1, (int) $link->collected_count);

        $this->get('/pay/l/'.$link->code)
            ->assertOk()
            ->assertSee('This link has been paid');

        $this->assertSame(1, Payment::where('business_id', $business->id)->count());
    }

    public function test_admin_index_lists_links_across_businesses(): void
    {
        $a = $this->business();
        $b = Business::create([
            'name' => 'Other Biz',
            'email' => 'otherbiz@test.com',
            'is_active' => true,
        ]);
        PaymentLink::create([
            'business_id' => $a->id,
            'title' => 'Alpha link',
            'amount' => 100,
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
        ]);
        PaymentLink::create([
            'business_id' => $b->id,
            'title' => 'Beta link',
            'amount' => null,
            'reuse_mode' => PaymentLink::REUSE_REUSABLE,
        ]);

        $admin = Admin::create([
            'name' => 'Boss',
            'email' => 'boss-links@test.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/enter0/payment-links')
            ->assertOk()
            ->assertSee('Alpha link')
            ->assertSee('Beta link')
            ->assertSee('Link Shop')
            ->assertSee('Other Biz');
    }

    public function test_create_preview_shows_card_and_account_options_when_cards_enabled(): void
    {
        $this->actingAs($this->business(['card_payments_enabled' => true]), 'business')
            ->get('/dashboard/payment-links/create')
            ->assertOk()
            ->assertSee('How do you want to pay?')
            ->assertSee('Card payment')
            ->assertSee('Account number');
    }

    public function test_public_pay_offers_card_and_account_when_cards_enabled_and_does_not_auto_create(): void
    {
        $business = $this->business(['card_payments_enabled' => true]);
        $link = PaymentLink::create([
            'business_id' => $business->id,
            'title' => 'Choose method',
            'amount' => 1500,
            'currency' => 'NGN',
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);

        $this->get('/pay/l/'.$link->code)
            ->assertOk()
            ->assertSee('How do you want to pay?')
            ->assertSee('Card payment')
            ->assertSee('Account number')
            ->assertSee('Choose method');

        $this->assertSame(0, Payment::where('business_id', $business->id)->count());
    }

    public function test_public_pay_card_redirects_to_hosted_checkout(): void
    {
        $this->mockCardCheckout('https://checkout.paga.test/pay/link');
        $business = $this->business(['card_payments_enabled' => true]);
        $link = PaymentLink::create([
            'business_id' => $business->id,
            'title' => 'Card fees',
            'amount' => 3200,
            'currency' => 'NGN',
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);

        $this->post('/pay/l/'.$link->code, [
            'payer_name' => 'Ada Lovelace',
            'payment_method' => 'card',
            'email' => 'ada@example.com',
        ])->assertRedirect('https://checkout.paga.test/pay/link');

        $payment = Payment::where('business_id', $business->id)->first();
        $this->assertNotNull($payment);
        $this->assertTrue($payment->isMevonCardCheckout());
        $this->assertNull($payment->account_number);
        $this->assertSame('payment_link', $payment->email_data['service'] ?? null);
        $this->assertSame($link->id, (int) ($payment->email_data['payment_link_id'] ?? 0));
        $this->assertDatabaseHas('payment_link_payments', [
            'payment_link_id' => $link->id,
            'payment_id' => $payment->id,
        ]);
    }

    public function test_public_pay_account_number_still_creates_temp_va_when_cards_enabled(): void
    {
        $this->mockTempVa();
        $business = $this->business(['card_payments_enabled' => true]);
        $link = PaymentLink::create([
            'business_id' => $business->id,
            'title' => 'Bank fees',
            'amount' => 1800,
            'currency' => 'NGN',
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);

        $this->post('/pay/l/'.$link->code, [
            'payer_name' => 'Ada Lovelace',
            'payment_method' => 'bank_transfer',
        ])->assertRedirect();

        $payment = Payment::where('business_id', $business->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('9998887777', $payment->account_number);
        $this->assertFalse($payment->isMevonCardCheckout());
    }

    public function test_public_pay_rejects_card_when_cards_are_disabled(): void
    {
        $business = $this->business(['card_payments_enabled' => false]);
        $link = PaymentLink::create([
            'business_id' => $business->id,
            'title' => 'No cards',
            'amount' => 900,
            'currency' => 'NGN',
            'reuse_mode' => PaymentLink::REUSE_REUSABLE,
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);

        $this->from('/pay/l/'.$link->code)
            ->post('/pay/l/'.$link->code, [
                'payer_name' => 'Ada Lovelace',
                'payment_method' => 'card',
                'email' => 'ada@example.com',
            ])
            ->assertRedirect('/pay/l/'.$link->code)
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Payment::where('business_id', $business->id)->count());
    }

    public function test_public_pay_card_requires_email(): void
    {
        $business = $this->business(['card_payments_enabled' => true]);
        $link = PaymentLink::create([
            'business_id' => $business->id,
            'title' => 'Need email',
            'amount' => 500,
            'currency' => 'NGN',
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);

        $this->from('/pay/l/'.$link->code)
            ->post('/pay/l/'.$link->code, [
                'payer_name' => 'Ada Lovelace',
                'payment_method' => 'card',
            ])
            ->assertRedirect('/pay/l/'.$link->code)
            ->assertSessionHasErrors('email');
    }

    public function test_status_endpoint_reports_pending_then_paid(): void
    {
        $this->mockTempVa();
        $business = $this->business();
        $link = PaymentLink::create([
            'business_id' => $business->id,
            'title' => 'Status check',
            'amount' => 1200,
            'currency' => 'NGN',
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);

        $this->get('/pay/l/'.$link->code)->assertOk();
        $payment = Payment::where('business_id', $business->id)->first();
        $this->assertNotNull($payment);

        $this->getJson('/pay/l/'.$link->code.'/status?payment_id='.$payment->id)
            ->assertOk()
            ->assertJsonPath('paid', false)
            ->assertJsonPath('status', Payment::STATUS_PENDING)
            ->assertJsonPath('message', 'Waiting for payment');

        $payment->update(['status' => Payment::STATUS_APPROVED]);

        $this->getJson('/pay/l/'.$link->code.'/status?payment_id='.$payment->id)
            ->assertOk()
            ->assertJsonPath('paid', true)
            ->assertJsonPath('message', 'Payment received')
            ->assertJsonPath('redirect_url', route('payment-links.pay', [
                'code' => $link->code,
                'payment_id' => $payment->id,
            ]));

        $this->assertSame(PaymentLink::STATUS_PAID, $link->fresh()->status);
    }

    public function test_status_endpoint_rejects_unknown_payment(): void
    {
        $business = $this->business();
        $link = PaymentLink::create([
            'business_id' => $business->id,
            'title' => 'No payment',
            'amount' => 400,
            'reuse_mode' => PaymentLink::REUSE_ONE_TIME,
            'status' => PaymentLink::STATUS_ACTIVE,
        ]);

        $this->getJson('/pay/l/'.$link->code.'/status?payment_id=999')
            ->assertNotFound();
    }
}
