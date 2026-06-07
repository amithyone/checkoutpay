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
        $card->update(['card_balance_usd' => 5]);

        Http::fake([
            'https://mevon.test/V1/card_details' => Http::response([
                'success' => true,
                'data' => [
                    'card_id' => 'VCARD-TEST-001',
                    'balance' => 22.75,
                ],
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/consumer/cards?refresh=1');

        $response->assertOk()
            ->assertJsonPath('data.operable_request.card_balance_usd', 22.75);
        $this->assertSame(22.75, (float) $card->fresh()->card_balance_usd);
        Http::assertSent(fn ($request) => $request->url() === 'https://mevon.test/V1/card_details');
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
