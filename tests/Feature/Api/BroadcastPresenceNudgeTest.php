<?php

namespace Tests\Feature\Api;

use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class BroadcastPresenceNudgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_presence_nudge_uses_registry_merchant_name_in_fcm(): void
    {
        config([
            'broadcast.pay_at_shop_proximity_push_enabled' => true,
            'broadcast.pay_at_shop_proximity_push_title' => 'Checkout Nearby Available',
            'services.firebase.checkoutnow.project_id' => 'test-project',
            'services.firebase.checkoutnow.service_account_json' => '{"client_email":"x@y.z","private_key":"x"}',
        ]);

        $businessId = DB::table('businesses')->insertGetId([
            'name' => 'MIDAS AGRO',
            'email' => 'midas@example.com',
            'is_active' => 1,
            'broadcast_pay_at_shop_enabled' => 1,
            'broadcast_pay_at_shop_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'CP-1RK8Z',
            'merchant_id' => 'MCH-CP-1RK8Z',
            'api_key' => 'bk_test_api_key_presence_nudge',
            'signing_key' => '',
            'public_key' => 'test-public-key',
            'signature_alg' => 'ED25519',
            'merchant_name' => 'MIDAS AGRO',
            'bank_name' => 'RUBIES MFB',
            'bank_name_hash' => 'sha256:abc',
            'masked_account_suffix' => '***4863',
            'account_number' => '1000004863',
            'recipient_bank_code' => '090175',
            'business_id' => $businessId,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348011111111',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 5000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => '2348011111111',
            'fcm_token' => 'fcm-token-pay-at-shop',
            'fcm_platform' => 'android',
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('isConfigured')->with(PushNotificationService::PROFILE_CHECKOUTNOW)->andReturn(true);
        $push->shouldReceive('sendToTokens')
            ->once()
            ->withArgs(function ($tokens, $title, $body, $data, $channel, $profile) {
                return $tokens === [['token' => 'fcm-token-pay-at-shop', 'platform' => 'android']]
                    && $title === 'Checkout Nearby Available'
                    && $body === 'MIDAS AGRO is open — tap to pay'
                    && $data['type'] === 'pay_at_shop'
                    && $data['terminal_id'] === 'CP-1RK8Z'
                    && $data['merchant_name'] === 'MIDAS AGRO'
                    && $data['session_kind'] === 'presence'
                    && $profile === PushNotificationService::PROFILE_CHECKOUTNOW;
            })
            ->andReturn([]);
        $this->app->instance(PushNotificationService::class, $push);

        $response = $this->postJson('/api/v1/broadcast/presence/nudge', [
            'terminal_id' => 'CP-1RK8Z',
            'session_kind' => 'presence',
            'device_name' => 'DESKTOP-ABC123',
        ], [
            'X-Terminal-Api-Key' => 'bk_test_api_key_presence_nudge',
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'sent' => 1,
                'merchant_name' => 'MIDAS AGRO',
                'title' => 'Checkout Nearby Available',
                'body' => 'MIDAS AGRO is open — tap to pay',
            ]);
    }
}
