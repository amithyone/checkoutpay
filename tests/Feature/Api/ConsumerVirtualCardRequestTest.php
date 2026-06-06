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

class ConsumerVirtualCardRequestTest extends TestCase
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
        Setting::set('virtual_card_fx_mid_usd_ngn', 1370, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_sell_profit_ngn', 15, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_buy_profit_ngn', 30, 'float', 'virtual_card', 'test');
    }

    public function test_async_card_request_sets_preparing_without_refund(): void
    {
        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'data' => ['bal' => '500000', 'usd_balance' => '20.00'],
            ], 200),
            'https://mevon.test/V1/card_request' => Http::response([
                'status' => false,
                'message' => 'Card creation request processed successfully',
                'data' => [],
            ], 200),
        ]);

        [$wallet, $account] = $this->walletTier2();
        Sanctum::actingAs($account, ['consumer']);

        $beforeBalance = (float) $wallet->balance;

        $response = $this->postJson('/api/v1/consumer/cards/request', [
            'pin' => '2468',
            'card_name' => 'Test User',
            'home_number' => '12',
            'home_address' => 'Lagos',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.preparing', true)
            ->assertJsonPath('data.request.status', VirtualCardRequest::STATUS_PREPARING);

        $wallet->refresh();
        $this->assertSame($beforeBalance - 6925.0, (float) $wallet->balance);

        $row = VirtualCardRequest::query()->where('whatsapp_wallet_id', $wallet->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(VirtualCardRequest::STATUS_PREPARING, $row->status);
        $this->assertDatabaseHas('virtual_card_request_logs', [
            'virtual_card_request_id' => $row->id,
            'event' => 'fee_held_awaiting_webhook',
        ]);
    }

    public function test_duplicate_request_blocked_while_preparing(): void
    {
        [$wallet, $account] = $this->walletTier2();

        VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_PREPARING,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-EXISTING',
            'card_name' => 'Test User',
            'home_number' => '12',
            'home_address' => 'Lagos',
            'request_payload' => ['email' => 'test@example.com'],
        ]);

        Sanctum::actingAs($account, ['consumer']);

        $response = $this->postJson('/api/v1/consumer/cards/request', [
            'pin' => '2468',
            'card_name' => 'Test User',
            'home_number' => '12',
            'home_address' => 'Lagos',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.preparing', true);

        $this->assertSame(1, VirtualCardRequest::query()->where('whatsapp_wallet_id', $wallet->id)->count());
    }

    public function test_request_when_card_already_active_returns_manage_status(): void
    {
        Http::fake();
        [$wallet, $account] = $this->walletTier2();
        Sanctum::actingAs($account, ['consumer']);

        VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_ACTIVE,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-ALREADY-HAVE',
            'card_external_id' => 'MEVON-CARD-EXISTING',
            'card_name' => 'Existing Card',
        ]);

        $response = $this->postJson('/api/v1/consumer/cards/request', [
            'pin' => '2468',
            'card_name' => 'Existing Card',
            'home_number' => '12',
            'home_address' => 'Lagos',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.already_has_card', true)
            ->assertJsonPath('data.card_screen', 'manage')
            ->assertJsonPath('data.has_active_card', true);
    }

    public function test_card_status_returns_operable_request_when_latest_attempt_failed(): void
    {
        [$wallet, $account] = $this->walletTier2();
        Sanctum::actingAs($account, ['consumer']);

        VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_ACTIVE,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-ACTIVE-OLD',
            'card_external_id' => 'MEVON-CARD-ACTIVE',
            'card_name' => 'Active Card',
            'created_at' => now()->subDay(),
        ]);

        VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_FAILED,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-FAILED-NEW',
            'failure_reason' => 'provider timeout',
            'card_name' => 'Retry Card',
        ]);

        $response = $this->getJson('/api/v1/consumer/cards');

        $response->assertOk()
            ->assertJsonPath('data.has_active_card', true)
            ->assertJsonPath('data.card_screen', 'manage')
            ->assertJsonPath('data.operable_request.card_external_id', 'MEVON-CARD-ACTIVE')
            ->assertJsonPath('data.operable_request.can_manage', true)
            ->assertJsonPath('data.latest_request.status', VirtualCardRequest::STATUS_FAILED)
            ->assertJsonPath('data.can_request_card', false)
            ->assertJsonPath('data.card_preparing', false);
    }

    /**
     * @return array{0: WhatsappWallet, 1: ConsumerWalletApiAccount}
     */
    private function walletTier2(): array
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
            'kyc_dob' => '1990-01-01',
            'card_home_number' => '12',
            'card_home_address' => 'Lagos',
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        return [$wallet, $account];
    }
}
