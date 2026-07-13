<?php

namespace Tests\Feature\Api;

use App\Events\PaymentApproved;
use App\Models\AccountNumber;
use App\Models\Business;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Live smoke (after deploy / with real MEVONPAY_SECRET_KEY):
 * 1. Enable card_payments_enabled on a test business in admin.
 * 2. POST /api/v1/payment-request with payment_method=card, email, amount.
 * 3. Open data.card_checkout.checkout_url and complete a test card payment.
 * 4. Confirm checkout.success webhook → payment approved, balance credited, merchant payment.approved.
 * 5. Control: omit payment_method (or bank_transfer) and confirm account_number is still assigned.
 */
class MevonCardCheckoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();

        config([
            'services.mevonpay.base_url' => 'https://mevonpay.test',
            'services.mevonpay.secret_key' => 'test-secret-key',
            'services.mevonpay.card_checkout_path' => '/V1/card_checkout',
            'services.mevonpay.card_checkout_auth' => 'raw',
            'services.mevonpay.webhook_secret' => '',
            'services.mevonpay.webhook_allowed_ips' => [],
            'services.mevonpay.webhook_allowed_domains' => [],
        ]);

        $this->mock(\App\Services\RevenueService::class, function ($mock): void {
            $mock->shouldReceive('recordTransaction')->andReturn(null);
        });

        Notification::fake();
    }

    /** @test */
    public function card_payment_request_returns_checkout_url_when_enabled(): void
    {
        Http::fake([
            'mevonpay.test/*' => Http::response([
                'status' => true,
                'message' => 'OK',
                'data' => [
                    'checkout_url' => 'https://checkout.paga.test/pay/abc',
                    'payment_reference' => 'PAY_TEST_REF_1',
                ],
            ], 200),
        ]);

        $business = $this->business(['card_payments_enabled' => true]);

        $response = $this->postJson('/api/v1/payment-request', [
            'amount' => 200,
            'payment_method' => 'card',
            'email' => 'customer@example.com',
            'payer_name' => 'Jane Doe',
        ], [
            'X-API-Key' => $business->api_key,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.payment_method', 'card');
        $response->assertJsonPath('data.card_checkout.checkout_url', 'https://checkout.paga.test/pay/abc');
        $response->assertJsonPath('data.card_checkout.payment_reference', 'PAY_TEST_REF_1');
        $this->assertNull($response->json('data.account_number'));

        $payment = Payment::where('transaction_id', $response->json('data.transaction_id'))->first();
        $this->assertNotNull($payment);
        $this->assertSame(Payment::SOURCE_EXTERNAL_MEVONPAY_CARD, $payment->payment_source);
        $this->assertSame('PAY_TEST_REF_1', $payment->external_reference);
        $this->assertTrue($payment->isMevonCardCheckout());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/V1/card_checkout')
                && $request->hasHeader('Authorization', 'test-secret-key')
                && ($request['action'] ?? null) === 'checkout'
                && ($request['email'] ?? null) === 'customer@example.com';
        });
    }

    /** @test */
    public function card_payment_request_forbidden_when_not_enabled(): void
    {
        $business = $this->business(['card_payments_enabled' => false]);

        $response = $this->postJson('/api/v1/payment-request', [
            'amount' => 200,
            'payment_method' => 'card',
            'email' => 'customer@example.com',
        ], [
            'X-API-Key' => $business->api_key,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('not enabled', (string) $response->json('message'));
    }

    /** @test */
    public function card_payment_request_requires_email(): void
    {
        $business = $this->business(['card_payments_enabled' => true]);

        $response = $this->postJson('/api/v1/payment-request', [
            'amount' => 200,
            'payment_method' => 'card',
            'payer_name' => 'Jane',
        ], [
            'X-API-Key' => $business->api_key,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function checkout_success_webhook_approves_and_credits_balance(): void
    {
        Event::fake([PaymentApproved::class]);

        $business = $this->business([
            'card_payments_enabled' => true,
            'balance' => 1000,
        ]);

        $ref = 'PAY_WH_REF_'.uniqid();
        $payment = Payment::create([
            'transaction_id' => 'TXN-CARD-WH-'.uniqid(),
            'amount' => 200,
            'payer_name' => 'jane',
            'webhook_url' => 'https://merchant.test/webhook',
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'payment_source' => Payment::SOURCE_EXTERNAL_MEVONPAY_CARD,
            'external_reference' => $ref,
            'email_data' => [
                'payment_method' => Payment::METHOD_CARD,
                'skip_auto_match' => true,
                'card_checkout' => [
                    'checkout_url' => 'https://checkout.paga.test/pay/x',
                    'payment_reference' => $ref,
                ],
            ],
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->postJson('/api/v1/webhook/mevonpay', [
            'event' => 'checkout.success',
            'data' => [
                'payment_reference' => $ref,
                'amount' => 197,
                'gross_amount' => 200,
                'charge_applied' => 3,
                'customer_email' => 'customer@example.com',
                'customer_phone' => '08012345678',
                'timestamp' => now()->toISOString(),
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_APPROVED, $payment->status);
        $this->assertSame(Payment::METHOD_CARD, $payment->payment_method_used);
        $this->assertEquals(197.0, (float) $payment->received_amount);
        $this->assertSame(197.0, (float) data_get($payment->email_data, 'mevon_card_checkout.amount'));
        $this->assertSame(200.0, (float) data_get($payment->email_data, 'gross_amount'));
        $this->assertSame(3.0, (float) data_get($payment->email_data, 'charge_applied'));

        $business->refresh();
        $this->assertGreaterThan(1000, (float) $business->balance);

        Event::assertDispatched(PaymentApproved::class, function (PaymentApproved $event) use ($payment) {
            return $event->payment->id === $payment->id
                && $event->payment->payment_method_used === Payment::METHOD_CARD;
        });

        $balanceAfter = (float) $business->fresh()->balance;
        $second = $this->postJson('/api/v1/webhook/mevonpay', [
            'event' => 'checkout.success',
            'data' => [
                'payment_reference' => $ref,
                'amount' => 197,
                'gross_amount' => 200,
                'charge_applied' => 3,
            ],
        ]);
        $second->assertOk();
        $this->assertSame($balanceAfter, (float) $business->fresh()->balance);
    }

    /** @test */
    public function bank_transfer_payment_request_still_assigns_account_number_when_method_omitted(): void
    {
        Http::fake();

        $business = $this->business([
            'card_payments_enabled' => false,
            'uses_external_account_numbers' => false,
        ]);

        $account = AccountNumber::create([
            'account_number' => '0123456789',
            'account_name' => 'Pool Account',
            'bank_name' => 'Test Bank',
            'business_id' => null,
            'is_pool' => true,
            'is_external' => false,
            'is_active' => true,
        ]);

        $payment = app(PaymentService::class)->createPayment([
            'amount' => 1500,
            'payer_name' => 'John Doe',
            'webhook_url' => 'https://merchant.test/webhook',
        ], $business);

        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertNotEmpty($payment->account_number);
        $this->assertSame($account->account_number, $payment->account_number);
        $this->assertFalse($payment->isMevonCardCheckout());
        $this->assertNotSame(Payment::SOURCE_EXTERNAL_MEVONPAY_CARD, $payment->payment_source);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function business(array $overrides = []): Business
    {
        return Business::create(array_merge([
            'name' => 'Card Test Biz',
            'email' => 'card-biz-'.uniqid().'@test.com',
            'api_key' => 'pk_card_'.uniqid(),
            'is_active' => true,
            'webhook_url' => 'https://merchant.test/webhook',
            'card_payments_enabled' => false,
            'uses_external_account_numbers' => false,
            'balance' => 0,
        ], $overrides));
    }

    private function ensureSchema(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');

        if (! $schema->hasTable('businesses')) {
            $schema->create('businesses', function (Blueprint $table) {
                $table->id();
                $table->string('business_id', 5)->nullable();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('api_key')->nullable();
                $table->string('webhook_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('website_approved')->default(false);
                $table->boolean('uses_external_account_numbers')->default(false);
                $table->boolean('whatsapp_wallet_api_enabled')->default(false);
                $table->boolean('card_payments_enabled')->default(false);
                $table->decimal('balance', 14, 2)->default(0);
                $table->decimal('charge_percentage', 8, 2)->nullable();
                $table->decimal('charge_fixed', 14, 2)->nullable();
                $table->boolean('charges_paid_by_customer')->default(false);
                $table->json('external_provider_settings')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! $schema->hasTable('business_websites')) {
            $schema->create('business_websites', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->string('website_url')->nullable();
                $table->string('webhook_url')->nullable();
                $table->boolean('is_approved')->default(false);
                $table->decimal('charge_percentage', 8, 2)->nullable();
                $table->decimal('charge_fixed', 14, 2)->nullable();
                $table->boolean('charges_paid_by_customer')->default(false);
                $table->boolean('charges_enabled')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! $schema->hasTable('payments')) {
            $schema->create('payments', function (Blueprint $table) {
                $table->id();
                $table->string('transaction_id')->unique();
                $table->decimal('amount', 14, 2);
                $table->string('status', 32)->default('pending');
                $table->unsignedBigInteger('business_id')->nullable();
                $table->unsignedBigInteger('business_website_id')->nullable();
                $table->unsignedBigInteger('developer_program_partner_business_id')->nullable();
                $table->string('payer_name')->nullable();
                $table->string('bank')->nullable();
                $table->string('account_number', 32)->nullable();
                $table->string('payment_source', 64)->nullable();
                $table->string('payment_method_used', 32)->nullable();
                $table->string('external_reference')->nullable();
                $table->string('checkout_pay_code', 6)->nullable();
                $table->timestamp('checkout_pay_code_expires_at')->nullable();
                $table->json('email_data')->nullable();
                $table->decimal('charge_percentage', 8, 2)->nullable();
                $table->decimal('charge_fixed', 14, 2)->nullable();
                $table->decimal('total_charges', 14, 2)->nullable();
                $table->decimal('business_receives', 14, 2)->nullable();
                $table->boolean('charges_paid_by_customer')->default(false);
                $table->decimal('received_amount', 14, 2)->nullable();
                $table->boolean('is_mismatch')->default(false);
                $table->string('mismatch_reason')->nullable();
                $table->string('webhook_url')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('matched_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! $schema->hasTable('account_numbers')) {
            $schema->create('account_numbers', function (Blueprint $table) {
                $table->id();
                $table->string('account_number')->unique();
                $table->string('account_name')->nullable();
                $table->string('bank_name')->nullable();
                $table->unsignedBigInteger('business_id')->nullable();
                $table->unsignedBigInteger('business_website_id')->nullable();
                $table->boolean('is_pool')->default(false);
                $table->boolean('is_invoice_pool')->default(false);
                $table->boolean('is_membership_pool')->default(false);
                $table->boolean('is_tickets_pool')->default(false);
                $table->boolean('is_external')->default(false);
                $table->string('external_provider')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('usage_count')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! $schema->hasTable('mevon_pay_ledger_entries')) {
            $schema->create('mevon_pay_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->string('direction')->nullable();
                $table->string('flow_type')->nullable();
                $table->decimal('gross_amount', 14, 2)->nullable();
                $table->decimal('mevon_inbound_fee', 14, 2)->nullable();
                $table->decimal('net_amount', 14, 2)->nullable();
                $table->string('external_reference')->nullable();
                $table->string('account_number')->nullable();
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('settings')) {
            $schema->create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string');
                $table->string('group')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('whatsapp_wallets')) {
            $schema->create('whatsapp_wallets', function (Blueprint $table) {
                $table->id();
                $table->string('phone_e164')->nullable();
                $table->unsignedBigInteger('linked_business_id')->nullable();
                $table->decimal('balance', 14, 2)->default(0);
                $table->string('status')->nullable();
                $table->string('tier')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! $schema->hasTable('external_apis')) {
            $schema->create('external_apis', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('provider_key')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('business_external_api')) {
            $schema->create('business_external_api', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->unsignedBigInteger('external_api_id');
                $table->string('assignment_mode')->nullable();
                $table->json('services')->nullable();
                $table->string('va_generation_mode')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('cache')) {
            $schema->create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }
    }
}
