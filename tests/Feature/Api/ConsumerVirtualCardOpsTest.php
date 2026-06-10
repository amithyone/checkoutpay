<?php

namespace Tests\Feature\Api;

use App\Models\ConsumerWalletApiAccount;
use App\Models\Setting;
use App\Models\VirtualCardRequest;
use App\Models\WhatsappWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerVirtualCardOpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'test-secret',
        ]);

        Setting::set('virtual_card_fx_mid_auto_sync', 0, 'boolean', 'virtual_card', 'test');
    }

    public function test_topup_debits_wallet_and_calls_provider(): void
    {
        [$wallet, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update(['card_balance_usd' => 5]);
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'vtu', 'test');
        Setting::set('virtual_card_fx_sell_profit_ngn', 0, 'float', 'virtual_card', 'test');

        Http::fake([
            'https://mevon.test/V1/card_topup' => Http::response([
                'status' => 'success',
                'message' => 'Card topup successful',
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/topup', [
            'pin' => '2468',
            'amount_usd' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request.card_balance_usd', 15);
        $wallet->refresh();
        $this->assertSame(14000.0, (float) $wallet->balance);
        $this->assertSame(15.0, (float) $card->fresh()->card_balance_usd);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://mevon.test/V1/card_topup'
                && (float) ($data['amount'] ?? 0) === 10.0
                && ($data['card_code'] ?? '') === 'VCARD-TEST-001';
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/V1/balance'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/V1/exchange'));
    }

    public function test_freeze_toggles_is_frozen(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'vtu', 'test');

        Http::fake([
            'https://mevon.test/V1/card_status' => Http::response([
                'status' => 'success',
                'message' => 'Card freeze successful',
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/status', [
            'pin' => '2468',
            'action' => 'freeze',
        ]);

        $response->assertOk();
        $this->assertTrue($card->fresh()->is_frozen);
    }

    public function test_freeze_repairs_placeholder_card_external_id_from_stored_payload(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update([
            'card_external_id' => '{card_id}',
            'card_details_payload' => [
                'card_number' => '4288520141503096',
                'cvv' => '486',
                'expiry' => '06/2029',
                'card_external_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
            ],
        ]);

        Http::fake([
            'https://mevon.test/V1/card_details' => Http::response([
                'success' => true,
                'data' => [
                    'card_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
                    'card_code' => 'VCARD2026060611150700359',
                ],
            ], 200),
            'https://mevon.test/V1/card_status' => Http::response([
                'status' => 'success',
                'message' => 'Card freeze successful',
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/status', [
            'pin' => '2468',
            'action' => 'freeze',
        ]);

        $response->assertOk();
        $this->assertSame('bab449bb-15e9-404a-aa73-657519df4794', $card->fresh()->card_external_id);
        $this->assertTrue($card->fresh()->is_frozen);
    }

    public function test_freeze_resolves_vcard_code_from_uuid_card_external_id(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update([
            'card_external_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
            'card_details_payload' => null,
        ]);

        Http::fake([
            'https://mevon.test/V1/card_details' => Http::response([
                'success' => true,
                'data' => [
                    'card_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
                    'card_code' => 'VCARD2026060611150700359',
                ],
            ], 200),
            'https://mevon.test/V1/card_status' => Http::response([
                'status' => 'success',
                'message' => 'Card freeze successful',
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/status', [
            'pin' => '2468',
            'action' => 'freeze',
        ]);

        $response->assertOk();
        $this->assertTrue($card->fresh()->is_frozen);
        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://mevon.test/V1/card_status') {
                return false;
            }

            return ($request->data()['card_code'] ?? '') === 'VCARD2026060611150700359';
        });
        $this->assertSame(
            'VCARD2026060611150700359',
            $card->fresh()->card_details_payload['card_code'] ?? null,
        );
    }

    public function test_topup_hides_merchant_usd_errors_from_user(): void
    {
        [$wallet, $account] = $this->walletWithActiveCard();
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'vtu', 'test');
        Setting::set('virtual_card_fx_sell_profit_ngn', 0, 'float', 'virtual_card', 'test');

        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'data' => [
                    'bal' => '0',
                    'usd_balance' => '0.00',
                ],
            ], 200),
            'https://mevon.test/V1/card_topup' => Http::response([
                'status' => false,
                'message' => 'Declined - Insufficient USD wallet balance.',
            ], 400),
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/topup', [
            'pin' => '2468',
            'amount_usd' => 10,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Card top-up could not be completed. Your wallet has been refunded.');
        $wallet->refresh();
        $this->assertSame(30000.0, (float) $wallet->balance);
    }

    public function test_topup_uses_balance_from_provider_response_when_present(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update(['card_balance_usd' => 5]);
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'vtu', 'test');
        Setting::set('virtual_card_fx_sell_profit_ngn', 0, 'float', 'virtual_card', 'test');

        Http::fake([
            'https://mevon.test/V1/card_topup' => Http::response([
                'status' => 'success',
                'message' => 'Card topup successful',
                'data' => ['balance' => 18.5],
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/topup', [
            'pin' => '2468',
            'amount_usd' => 10,
        ]);

        $response->assertOk()->assertJsonPath('data.request.card_balance_usd', 18.5);
        $this->assertSame(18.5, (float) $card->fresh()->card_balance_usd);
    }

    public function test_topup_skips_ngn_conversion_when_wallet_usd_is_already_sufficient(): void
    {
        [$wallet, $account] = $this->walletWithActiveCard();
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'vtu', 'test');
        Setting::set('virtual_card_fx_sell_profit_ngn', 0, 'float', 'virtual_card', 'test');

        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'data' => [
                    'bal' => '500000',
                    'usd_balance' => '32.31',
                    'usd_ledger_bal' => '0.00',
                ],
            ], 200),
            'https://mevon.test/V1/card_topup' => Http::response([
                'status' => 'success',
                'message' => 'Card topup successful',
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/topup', [
            'pin' => '2468',
            'amount_usd' => 10,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $wallet->refresh();
        $this->assertSame(14000.0, (float) $wallet->balance);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/V1/exchange'));
    }

    public function test_topup_retries_merchant_usd_buy_when_provider_reports_low_float(): void
    {
        [$wallet, $account] = $this->walletWithActiveCard();
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'vtu', 'test');
        Setting::set('virtual_card_fx_sell_profit_ngn', 0, 'float', 'virtual_card', 'test');

        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'data' => ['bal' => '500000', 'usd_balance' => '32.31', 'usd_ledger_bal' => '0.00'],
            ], 200),
            'https://mevon.test/V1/card_topup' => Http::sequence()
                ->push([
                    'status' => false,
                    'message' => 'Insufficient USD balance',
                ], 200)
                ->push([
                    'status' => 'success',
                    'message' => 'Card topup successful',
                ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/topup', [
            'pin' => '2468',
            'amount_usd' => 10,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $wallet->refresh();
        $this->assertSame(14000.0, (float) $wallet->balance);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/V1/exchange'));
    }

    public function test_topup_returns_insufficient_balance_when_user_wallet_is_low(): void
    {
        [$wallet, $account] = $this->walletWithActiveCard();
        $wallet->update(['balance' => 100]);
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'vtu', 'test');
        Setting::set('virtual_card_fx_sell_profit_ngn', 0, 'float', 'virtual_card', 'test');

        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'data' => ['bal' => '500000', 'usd_balance' => '50.00'],
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/topup', [
            'pin' => '2468',
            'amount_usd' => 10,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient balance.');
        $wallet->refresh();
        $this->assertSame(100.0, (float) $wallet->balance);
    }

    public function test_quote_returns_sell_rate_amount(): void
    {
        [, $account] = $this->walletWithActiveCard();
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'vtu', 'test');
        Setting::set('virtual_card_fx_sell_profit_ngn', 48, 'float', 'virtual_card', 'test');

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/consumer/cards/quote?amount_usd=10&action=topup');

        $response->assertOk()
            ->assertJsonPath('data.amount_ngn', 16480)
            ->assertJsonPath('data.sell_rate', 1648);
    }

    public function test_card_details_requires_pin_and_returns_stored_webhook_fields(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update([
            'card_details_payload' => [
                'card_number' => '4288520141503096',
                'cvv' => '486',
                'expiry' => '06/2029',
                'last_four' => '3096',
                'card_name' => 'Test User',
                'brand' => 'visa',
                'balance_usd' => 5,
                'billing_address' => [
                    'street' => '3401 N. Miami, Ave. Ste 230',
                    'city' => 'Miami',
                    'state' => 'FL',
                    'country' => 'United States',
                    'zip_code' => '33127',
                ],
            ],
            'card_balance_usd' => 5,
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/details', [
            'pin' => '2468',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.card_number', '4288520141503096')
            ->assertJsonPath('data.cvv', '486')
            ->assertJsonPath('data.expiry', '06/29')
            ->assertJsonPath('data.card_external_id', 'VCARD-TEST-001')
            ->assertJsonPath('data.last_four', '3096')
            ->assertJsonPath('data.balance_usd', 5)
            ->assertJsonPath('data.billing_city', 'Miami')
            ->assertJsonPath('data.billing_state', 'FL')
            ->assertJsonPath('data.billing_zip', '33127')
            ->assertJsonPath('data.billing_country', 'United States');
    }

    public function test_card_details_defaults_empty_billing_state_to_florida_and_parses_expiry_month_year(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update([
            'card_details_payload' => [
                'card_number' => '4288520141503096',
                'cvv' => '486',
                'expiry' => '',
                'expiry_month_year' => '06/29',
                'last_four' => '3096',
                'card_name' => 'Test User',
                'brand' => 'visa',
                'balance_usd' => 5,
                'billing_address' => [
                    'street' => '3401 N. Miami, Ave. Ste 230',
                    'city' => 'Miami',
                    'state' => '',
                    'country' => 'United States',
                    'zip_code' => '33127',
                ],
            ],
            'card_balance_usd' => 5,
        ]);

        Http::fake([
            'https://mevon.test/V1/card_details' => Http::response([
                'success' => true,
                'data' => [
                    'card_id' => 'VCARD-TEST-001',
                    'card_code' => 'VCARD2026060611150700359',
                    'card_number' => '4288520141503096',
                    'cvv' => '486',
                    'expiry_month_year' => '06/29',
                    'balance' => 5,
                    'billing_address' => [
                        'street' => '3401 N. Miami, Ave. Ste 230',
                        'city' => 'Miami',
                        'state' => '',
                        'country' => 'United States',
                        'zip_code' => '33127',
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/details', [
            'pin' => '2468',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.card_number', '4288520141503096')
            ->assertJsonPath('data.cvv', '486')
            ->assertJsonPath('data.expiry', '06/29')
            ->assertJsonPath('data.billing_city', 'Miami')
            ->assertJsonPath('data.billing_state', 'Florida')
            ->assertJsonPath('data.billing_zip', '33127')
            ->assertJsonPath('data.billing_country', 'United States');
    }

    public function test_card_details_parses_string_billing_address(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update([
            'card_details_payload' => [
                'card_number' => '4288520141503096',
                'cvv' => '486',
                'expiry' => '06/29',
                'last_four' => '3096',
                'card_name' => 'Test User',
                'brand' => 'visa',
                'balance_usd' => 5,
                'billing_address' => '3401 N. Miami, Ave. Ste 230, Miami, Florida, 33127, United States',
            ],
            'card_balance_usd' => 10.50,
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/details', [
            'pin' => '2468',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.balance_usd', 10.50)
            ->assertJsonPath('data.billing_street', '3401 N. Miami, Ave. Ste 230')
            ->assertJsonPath('data.billing_city', 'Miami')
            ->assertJsonPath('data.billing_state', 'Florida')
            ->assertJsonPath('data.billing_zip', '33127')
            ->assertJsonPath('data.billing_country', 'United States');
    }

    public function test_card_details_parses_split_expiry_month_and_year_from_webhook(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);

        Http::fake([
            'https://mevon.test/V1/card_details' => Http::response([
                'success' => true,
                'data' => [
                    'card_id' => 'VCARD-TEST-001',
                    'card_code' => 'VCARD2026060611150700359',
                    'card_number' => '4288520141503096',
                    'cvv' => '486',
                    'exp_month' => '6',
                    'exp_year' => '2029',
                    'balance' => 5,
                    'billing_address' => '3401 N. Miami, Ave. Ste 230, Miami, Florida, 33127, United States',
                ],
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/details', [
            'pin' => '2468',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.card_number', '4288520141503096')
            ->assertJsonPath('data.cvv', '486')
            ->assertJsonPath('data.expiry', '06/29')
            ->assertJsonPath('data.billing_street', '3401 N. Miami, Ave. Ste 230')
            ->assertJsonPath('data.billing_city', 'Miami')
            ->assertJsonPath('data.billing_state', 'Florida')
            ->assertJsonPath('data.billing_zip', '33127')
            ->assertJsonPath('data.billing_country', 'United States');
    }


    public function test_card_details_backfills_from_orphan_webhook_log_by_card_id(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);

        \App\Models\VirtualCardRequestLog::query()->create([
            'virtual_card_request_id' => null,
            'whatsapp_wallet_id' => null,
            'level' => 'info',
            'event' => 'webhook_received',
            'message' => 'MevonPay card webhook received',
            'context' => [
                'raw_payload' => [
                    'event' => 'card.created.success',
                    'data' => [
                        'card_id' => 'VCARD-TEST-001',
                        'card_number' => '4288520141503096',
                        'cvv' => '486',
                        'expiry' => '06/2029',
                        'last4' => '3096',
                        'balance' => 5,
                    ],
                ],
            ],
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/details', [
            'pin' => '2468',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.card_number', '4288520141503096')
            ->assertJsonPath('data.cvv', '486');

        $card->refresh();
        $this->assertSame('4288520141503096', data_get($card->card_details_payload, 'card_number'));
    }

    public function test_card_status_includes_design_url_when_configured(): void
    {
        [, $account] = $this->walletWithActiveCard();
        Setting::set('virtual_card_design_image', 'settings/virtual-card/test-bg.png', 'string', 'virtual_card', 'test');

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/consumer/cards');

        $response->assertOk()
            ->assertJsonPath('data.card_design_url', url('storage/settings/virtual-card/test-bg.png'));
    }

    public function test_card_transactions_lists_wallet_card_activity(): void
    {
        [$wallet, $account] = $this->walletWithActiveCard();

        \App\Models\WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => \App\Models\WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
            'amount' => 8000,
            'balance_after' => 22000,
            'external_reference' => 'VCARD-REF-TEST',
            'meta' => [
                'fee_usd' => 5,
                'fx_mid_usd_ngn' => 1600,
                'sell_rate' => 1600,
            ],
        ]);

        \App\Models\WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => \App\Models\WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_TOPUP,
            'amount' => 16000,
            'balance_after' => 14000,
            'external_reference' => 'VCARD-TOP-TEST123',
            'meta' => [
                'amount_usd' => 10,
                'fx_mid_usd_ngn' => 1600,
                'sell_rate' => 1600,
            ],
        ]);

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/consumer/cards/transactions');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'virtual_card_topup')
            ->assertJsonPath('data.0.label', 'Fund card')
            ->assertJsonPath('data.1.type', 'virtual_card_fee');
    }

    public function test_card_status_refresh_queries_mevon_for_live_balance(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update([
            'card_balance_usd' => 5,
            'provider_reference' => 'REQ1779645711521',
        ]);

        Http::fake([
            'https://mevon.test/V1/card_balance' => Http::response([
                'success' => 1,
                'message' => 'Card balance updated successfully',
                'data' => [
                    'card_id' => '',
                    'balance' => 22.75,
                    'currency' => 'USD',
                ],
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/consumer/cards?refresh=1');

        $response->assertOk()
            ->assertJsonPath('data.operable_request.card_balance_usd', 22.75);
        $this->assertSame(22.75, (float) $card->fresh()->card_balance_usd);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://mevon.test/V1/card_balance'
                && ($data['request_id'] ?? '') === 'REQ1779645711521';
        });
        Http::assertNotSent(fn ($request) => $request->url() === 'https://mevon.test/V1/card_details');
    }

    public function test_card_status_refresh_uses_req_from_stored_webhook_for_card_balance(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update([
            'card_balance_usd' => 5,
            'provider_reference' => null,
            'card_external_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
            'response_payload' => [
                'webhook' => [
                    'event' => 'card.created.success',
                    'data' => [
                        'request_id' => 'REQ1780744493644',
                        'card_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
                        'balance' => 5,
                        'reference' => '766f5cdb-9956-4cec-af77-b520f624acc3',
                    ],
                ],
            ],
        ]);

        Http::fake([
            'https://mevon.test/V1/card_balance' => Http::response([
                'success' => true,
                'message' => 'Card balance updated successfully',
                'data' => ['balance' => 10, 'currency' => 'USD'],
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/consumer/cards?refresh=1');

        $response->assertOk()->assertJsonPath('data.operable_request.card_balance_usd', 10);
        $this->assertSame(10.0, (float) $card->fresh()->card_balance_usd);
        $this->assertSame('REQ1780744493644', $card->fresh()->provider_reference);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://mevon.test/V1/card_balance'
                && ($data['request_id'] ?? '') === 'REQ1780744493644';
        });
    }

    public function test_card_status_refresh_falls_back_to_card_details_without_request_id(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update(['card_balance_usd' => 5, 'provider_reference' => null]);

        Http::fake([
            'https://mevon.test/V1/card_details' => Http::response([
                'success' => true,
                'data' => [
                    'card_id' => 'VCARD-TEST-001',
                    'card_code' => 'VCARD2026060611150700359',
                    'card_number' => '4288520141503096',
                    'cvv' => '123',
                    'expiry_month_year' => '06/29',
                    'balance' => 12.5,
                ],
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/consumer/cards?refresh=1');

        $response->assertOk()->assertJsonPath('data.operable_request.card_balance_usd', 12.5);
        $this->assertSame(12.5, (float) $card->fresh()->card_balance_usd);
        Http::assertSent(fn ($request) => $request->url() === 'https://mevon.test/V1/card_details');
        Http::assertNotSent(fn ($request) => $request->url() === 'https://mevon.test/V1/card_balance');
    }

    public function test_card_transactions_includes_mevon_merchant_activity(): void
    {
        [, $account] = $this->walletWithActiveCard();

        Http::fake([
            'https://mevon.test/V1/card_transactions' => Http::response([
                'success' => true,
                'message' => 'Result successful',
                'data' => [[
                    'code' => '09eea695-e2c5-4620-9e70-86a25d19f28b',
                    'description' => 'Google CLOUD M9QWV5 Dublin IR',
                    'status' => 'success',
                    'reference' => '88942e4e978b',
                    'amount' => 10.00,
                    'amountInfo' => '$10.00',
                    'fee' => 0.00,
                    'currency' => 'USD',
                    'createdOn' => '2026-06-01T15:02:13.2763678',
                    'drcr' => 'DR',
                    'category' => 'withdraw card',
                ]],
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/consumer/cards/transactions');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.source', 'mevon')
            ->assertJsonPath('data.0.type', 'card_spend')
            ->assertJsonPath('data.0.amount_usd', 10)
            ->assertJsonPath('data.0.description', 'Google CLOUD M9QWV5 Dublin IR');

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://mevon.test/V1/card_transactions'
                && ($data['card_code'] ?? '') === 'VCARD-TEST-001';
        });
    }

    public function test_card_transactions_resolves_vcard_from_card_details_when_only_uuid_stored(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update([
            'card_external_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
            'provider_reference' => null,
            'card_details_payload' => null,
            'response_payload' => [
                'webhook' => [
                    'event' => 'card.created.success',
                    'data' => [
                        'request_id' => 'REQ1780744493644',
                        'card_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
                        'balance' => 5,
                    ],
                ],
            ],
        ]);

        Http::fake([
            'https://mevon.test/V1/card_details' => Http::response([
                'success' => true,
                'data' => [
                    'card_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
                    'card_code' => 'VCARD2026060611150700359',
                    'balance' => 10,
                ],
            ], 200),
            'https://mevon.test/V1/card_transactions' => Http::response([
                'success' => true,
                'data' => [[
                    'description' => 'Google CLOUD M9QWV5 Dublin IR',
                    'status' => 'success',
                    'amount' => 10.00,
                    'currency' => 'USD',
                    'createdOn' => '2026-06-01T15:02:13.2763678',
                    'category' => 'withdraw card',
                ]],
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/consumer/cards/transactions');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.source', 'mevon')
            ->assertJsonPath('data.0.description', 'Google CLOUD M9QWV5 Dublin IR');

        $fresh = $card->fresh();
        $this->assertSame('REQ1780744493644', $fresh->provider_reference);
        $this->assertSame('VCARD2026060611150700359', $fresh->card_details_payload['card_code'] ?? null);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://mevon.test/V1/card_details'
                && ($data['card_id'] ?? '') === 'bab449bb-15e9-404a-aa73-657519df4794';
        });
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://mevon.test/V1/card_transactions'
                && ($data['card_code'] ?? '') === 'VCARD2026060611150700359';
        });
        Http::assertNotSent(fn ($request) => $request->url() === 'https://mevon.test/V1/card_balance');
    }

    public function test_card_transactions_auto_freezes_after_declined_payment(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update([
            'auto_freeze_on_decline' => true,
            'is_frozen' => false,
        ]);

        Http::fake([
            'https://mevon.test/V1/card_transactions' => Http::response([
                'success' => true,
                'data' => [[
                    'code' => 'decline-ref-001',
                    'description' => 'Merchant declined',
                    'status' => 'failed',
                    'amount' => 4.50,
                    'currency' => 'USD',
                    'createdOn' => '2026-06-02T10:00:00.0000000',
                    'category' => 'declined card',
                ]],
            ], 200),
            'https://mevon.test/V1/card_status' => Http::response([
                'success' => true,
                'message' => 'Card freeze successful',
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/consumer/cards/transactions');

        $response->assertOk()
            ->assertJsonPath('meta.auto_frozen', true)
            ->assertJsonPath('data.0.status', 'failed');

        $this->assertTrue($card->fresh()->is_frozen);
        Http::assertSent(fn ($request) => $request->url() === 'https://mevon.test/V1/card_status');
    }

    public function test_user_can_toggle_auto_freeze_setting(): void
    {
        [, $account, $card] = $this->walletWithActiveCard(returnCard: true);
        $card->update(['auto_freeze_on_decline' => true]);

        Sanctum::actingAs($account);

        $this->postJson('/api/v1/consumer/cards/auto-freeze', ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.request.auto_freeze_on_decline', false);

        $this->assertFalse($card->fresh()->auto_freeze_on_decline);
    }

    public function test_balance_reconciliation_applies_spend_deduction_and_sets_pending_flag(): void
    {
        [$wallet, $account, $card] = $this->walletWithActiveCard(returnCard: true);

        // Set card starting balance to $138 on database, and mock MevonPay details balance also returning $138 (stale)
        $card->update([
            'card_balance_usd' => 138,
            'request_payload' => ['amount' => 138],
            'card_external_id' => 'VCARD2026060611150700359',
            'card_details_payload' => [
                'card_code' => 'VCARD2026060611150700359',
                'card_number' => '4288520141503096',
                'cvv' => '486',
                'expiry' => '06/2029',
                'last_four' => '3096',
                'card_name' => 'Test User',
                'brand' => 'visa',
                'balance_usd' => 138,
            ],
        ]);

        // Mock MevonPay endpoints: getCardDetails (stale balance 138) and getCardTransactions (with a successful spend of $37)
        Http::fake([
            'https://mevon.test/V1/card_details' => Http::response([
                'success' => true,
                'data' => [
                    'card_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
                    'card_code' => 'VCARD2026060611150700359',
                    'balance' => 138,
                ],
            ], 200),
            'https://mevon.test/V1/card_balance' => Http::response([
                'status' => 'success',
                'data' => [
                    'balance' => 138,
                ],
            ], 200),
            'https://mevon.test/V1/card_transactions' => Http::response([
                'status' => 'success',
                'data' => [
                    [
                        'code' => 'TXN001',
                        'reference' => 'ref-spend-1',
                        'drcr' => 'DR',
                        'amount' => 37.0,
                        'status' => 'completed',
                        'category' => 'Card Purchase',
                        'description' => 'Netflix.com',
                        'createdOn' => '2026-06-10T10:39:29+01:00',
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($account);

        // Call the details endpoint, which will trigger sync and refresh balance
        $response = $this->postJson('/api/v1/consumer/cards/details', [
            'pin' => '2468',
        ]);

        $response->assertOk();
        $data = $response->json('data');

        // We expect the balance to be reconciled: 138 (initial load) - 37 (Netflix spend) = 101.
        $this->assertEquals(101, $data['balance_usd']);
        $this->assertTrue($data['reconciliation_pending']);

        // Verify the database was updated with the correct reconciled balance and pending flag
        $card->refresh();
        $this->assertEquals(101.0, (float) $card->card_balance_usd);
        $this->assertTrue($card->reconciliation_pending);
    }

    /**
     * @return array{0: WhatsappWallet, 1: ConsumerWalletApiAccount, 2?: VirtualCardRequest}
     */
    private function walletWithActiveCard(bool $returnCard = false): array
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348148790554',
            'display_name' => 'Reviewer',
            'balance' => 30000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'kyc_fname' => 'Test',
            'kyc_lname' => 'User',
            'kyc_email' => 'test@example.com',
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        $card = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_ACTIVE,
            'fee_usd' => 5,
            'fee_ngn' => 8000,
            'fx_rate_used' => 1600,
            'external_reference' => 'VCARD-REF-TEST',
            'card_external_id' => 'VCARD-TEST-001',
            'card_name' => 'Test User',
            'home_number' => '12',
            'home_address' => 'Abuja',
        ]);

        return $returnCard ? [$wallet, $account, $card] : [$wallet, $account];
    }
}
