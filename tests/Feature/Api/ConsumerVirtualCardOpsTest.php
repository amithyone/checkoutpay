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
    }

    public function test_topup_debits_wallet_and_calls_provider(): void
    {
        [$wallet, $account] = $this->walletWithActiveCard();
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'vtu', 'test');
        Setting::set('virtual_card_fx_sell_markup_percent', 0, 'float', 'vtu', 'test');

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

        $response->assertOk()->assertJsonPath('success', true);
        $wallet->refresh();
        $this->assertSame(14000.0, (float) $wallet->balance);
        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://mevon.test/V1/card_topup'
                && (float) ($data['amount'] ?? 0) === 10.0
                && ($data['card_code'] ?? '') === 'VCARD-TEST-001';
        });
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

    public function test_quote_returns_sell_rate_amount(): void
    {
        [, $account] = $this->walletWithActiveCard();
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'vtu', 'test');
        Setting::set('virtual_card_fx_sell_markup_percent', 3, 'float', 'vtu', 'test');

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/consumer/cards/quote?amount_usd=10&action=topup');

        $response->assertOk()
            ->assertJsonPath('data.amount_ngn', 16480)
            ->assertJsonPath('data.sell_rate', 1648);
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
