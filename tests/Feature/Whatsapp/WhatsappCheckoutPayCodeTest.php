<?php

namespace Tests\Feature\Whatsapp;

use App\Events\PaymentApproved;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletPartnerPayIntent;
use App\Services\Whatsapp\WhatsappCheckoutPayCodeHandler;
use App\Services\Whatsapp\WhatsappCheckoutPayCodePolicy;
use App\Services\Whatsapp\WhatsappCheckoutPayCodeService;
use App\Services\Whatsapp\WhatsappCheckoutPayCodeSettlementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class WhatsappCheckoutPayCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
        config(['checkout.whatsapp_wallet.contact_url' => 'https://wa.me/2348012345678']);

        $this->mock(\App\Services\RevenueService::class, function ($mock): void {
            $mock->shouldReceive('recordTransaction')->andReturn(null);
        });
    }

    /** @test */
    public function attach_to_payment_adds_whatsapp_pay_block_when_business_enabled(): void
    {
        $business = $this->businessWithWalletApi(true);

        $payment = Payment::create([
            'transaction_id' => 'TXN-PAYCODE1',
            'amount' => 5000,
            'payer_name' => 'ada',
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'account_number' => '0123456789',
            'expires_at' => now()->addHour(),
        ]);

        $payload = app(WhatsappCheckoutPayCodeService::class)->attachToPayment($payment, $business);

        $this->assertNotNull($payload);
        $this->assertSame('PAY '.$payload['code'], $payload['message']);
        $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{6}$/', $payload['code']);
        $this->assertStringContainsString('wa.me/2348012345678', (string) $payload['wa_link']);
        $this->assertSame(['NG'], $payload['enabled_countries']);
        $payment->refresh();
        $this->assertNotNull($payment->checkout_pay_code);
    }

    /** @test */
    public function attach_skipped_when_whatsapp_wallet_api_disabled(): void
    {
        $business = $this->businessWithWalletApi(false);

        $payment = Payment::create([
            'transaction_id' => 'TXN-PAYCODE2',
            'amount' => 5000,
            'payer_name' => 'ada',
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'account_number' => '0123456789',
            'expires_at' => now()->addHour(),
        ]);

        $payload = app(WhatsappCheckoutPayCodeService::class)->attachToPayment($payment, $business);

        $this->assertNull($payload);
    }

    /** @test */
    public function settlement_approves_linked_payment_and_debits_wallet(): void
    {
        Event::fake([PaymentApproved::class]);

        $business = $this->businessWithWalletApi(true);
        $wallet = WhatsappWallet::create([
            'phone_e164' => '2348012345678',
            'balance' => 10000,
            'pin_hash' => Hash::make('1234'),
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        $payment = Payment::create([
            'transaction_id' => 'TXN-SETTLE1',
            'amount' => 5000,
            'payer_name' => 'ada',
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'account_number' => '0123456789',
            'checkout_pay_code' => 'ABC12',
            'checkout_pay_code_expires_at' => now()->addMinutes(30),
            'expires_at' => now()->addHour(),
        ]);

        $result = app(WhatsappCheckoutPayCodeSettlementService::class)->settle(
            $payment,
            $business,
            $wallet,
            'test-idempotency-key-001',
        );

        $this->assertTrue($result['ok'] ?? false, $result['message'] ?? 'settlement failed');
        $payment->refresh();
        $wallet->refresh();

        $this->assertSame(Payment::STATUS_APPROVED, $payment->status);
        $this->assertSame(Payment::METHOD_WHATSAPP_WALLET, $payment->payment_method_used);
        $this->assertNull($payment->checkout_pay_code);
        $this->assertSame(5000.0, (float) $wallet->balance);

        Event::assertDispatched(PaymentApproved::class);
    }

    /** @test */
    public function bank_approve_invalidates_checkout_pay_code(): void
    {
        $business = $this->businessWithWalletApi(false);

        $payment = Payment::create([
            'transaction_id' => 'TXN-BANKWIN',
            'amount' => 5000,
            'payer_name' => 'ada',
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'account_number' => '0123456789',
            'checkout_pay_code' => 'XYZ99',
            'checkout_pay_code_expires_at' => now()->addMinutes(30),
            'expires_at' => now()->addHour(),
        ]);

        WhatsappWalletPartnerPayIntent::create([
            'business_id' => $payment->business_id,
            'payment_id' => $payment->id,
            'confirm_token' => str_repeat('a', 48),
            'phone_e164' => '2348012345678',
            'amount' => 5000,
            'order_reference' => $payment->transaction_id,
            'order_summary' => 'Test',
            'payer_name' => 'ada',
            'webhook_url' => 'https://example.com/hook',
            'client_idempotency_key' => 'checkout-pay-code-'.$payment->id,
            'status' => WhatsappWalletPartnerPayIntent::STATUS_PENDING_PIN,
            'expires_at' => now()->addMinutes(30),
        ]);

        $payment->approve(['amount' => 5000], false, 5000.0);

        $payment->refresh();
        $this->assertNull($payment->checkout_pay_code);
        $this->assertSame(Payment::METHOD_BANK_TRANSFER, $payment->payment_method_used);

        $intent = WhatsappWalletPartnerPayIntent::query()->where('payment_id', $payment->id)->first();
        $this->assertSame(WhatsappWalletPartnerPayIntent::STATUS_EXPIRED, $intent->status);
    }

    /** @test */
    public function policy_defaults_to_nigeria_only(): void
    {
        DB::table('settings')->where('key', 'whatsapp_checkout_pay_code_enabled_countries')->delete();

        $this->assertSame(['NG'], WhatsappCheckoutPayCodePolicy::enabledCountries());
        $this->assertTrue(WhatsappCheckoutPayCodePolicy::customerCountryAllowed('2348012345678'));
        $this->assertFalse(WhatsappCheckoutPayCodePolicy::customerCountryAllowed('264811234567'));
    }

    /** @test */
    public function find_active_payment_accepts_legacy_five_char_code(): void
    {
        $business = $this->businessWithWalletApi(true);

        $payment = Payment::create([
            'transaction_id' => 'TXN-LEGACY5',
            'amount' => 5000,
            'payer_name' => 'ada',
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'account_number' => '0123456789',
            'checkout_pay_code' => 'ABC12',
            'checkout_pay_code_expires_at' => now()->addMinutes(30),
            'expires_at' => now()->addHour(),
        ]);

        $found = app(WhatsappCheckoutPayCodeService::class)->findActivePaymentByCode('abc12');
        $this->assertNotNull($found);
        $this->assertSame($payment->id, $found->id);
    }

    /** @test */
    public function handler_rejects_disallowed_country(): void
    {
        Setting::set('whatsapp_checkout_pay_code_enabled_countries', ['NG'], 'json', 'whatsapp');

        $client = Mockery::mock(\App\Services\Whatsapp\EvolutionWhatsAppClient::class);
        $client->shouldReceive('sendText')->once()->andReturn(true);
        $this->app->instance(\App\Services\Whatsapp\EvolutionWhatsAppClient::class, $client);

        $handler = app(WhatsappCheckoutPayCodeHandler::class);
        $handled = $handler->tryHandle('Whatsapp', '264811234567', 'PAY ABCDE');

        $this->assertTrue($handled);
    }

    private function businessWithWalletApi(bool $enabled): Business
    {
        return Business::create([
            'name' => 'Test Business',
            'email' => uniqid('biz').'@test.com',
            'api_key' => 'pk_'.uniqid(),
            'is_active' => true,
            'whatsapp_wallet_api_enabled' => $enabled,
            'balance' => 0,
        ]);
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
                $table->string('api_key')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('whatsapp_wallet_api_enabled')->default(false);
                $table->decimal('balance', 14, 2)->default(0);
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
                $table->string('payer_name')->nullable();
                $table->string('account_number', 32)->nullable();
                $table->string('payment_source', 64)->nullable();
                $table->string('payment_method_used', 32)->nullable();
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
                $table->string('phone_e164')->unique();
                $table->decimal('balance', 14, 2)->default(0);
                $table->string('pin_hash')->nullable();
                $table->unsignedTinyInteger('tier')->default(1);
                $table->string('status', 32)->default('active');
                $table->decimal('daily_transfer_total', 14, 2)->default(0);
                $table->date('daily_transfer_for_date')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('whatsapp_wallet_transactions')) {
            $schema->create('whatsapp_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->string('sender_name')->nullable();
                $table->string('type', 64);
                $table->decimal('amount', 14, 2);
                $table->decimal('balance_after', 14, 2)->nullable();
                $table->string('external_reference')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('whatsapp_wallet_partner_pay_intents')) {
            $schema->create('whatsapp_wallet_partner_pay_intents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->string('confirm_token', 64);
                $table->string('phone_e164');
                $table->decimal('amount', 14, 2);
                $table->string('order_reference');
                $table->text('order_summary');
                $table->string('payer_name');
                $table->string('webhook_url')->nullable();
                $table->string('client_idempotency_key');
                $table->string('status', 32);
                $table->text('failure_reason')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('partner_wallet_spends')) {
            $schema->create('partner_wallet_spends', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->string('idempotency_key');
                $table->string('phone_e164');
                $table->decimal('amount', 14, 2);
                $table->string('status', 32);
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->unsignedBigInteger('whatsapp_wallet_transaction_id')->nullable();
                $table->json('response_payload')->nullable();
                $table->timestamps();
            });
        }
    }
}
