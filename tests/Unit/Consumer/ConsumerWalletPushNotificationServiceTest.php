<?php

namespace Tests\Unit\Consumer;

use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletPushNotificationService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConsumerWalletPushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'consumer_wallet.credit_push_enabled' => true,
            'consumer_wallet.credit_push_channel' => 'money_received',
            'services.firebase.checkoutnow.project_id' => 'test-project',
            'services.firebase.checkoutnow.service_account_json' => '{"client_email":"x@y.z","private_key":"x"}',
        ]);
    }

    public function test_wallet_credit_push_uses_fcm_token(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348012345678',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 10000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => '2348012345678',
            'fcm_token' => 'fcm-token-wallet-credit',
            'fcm_platform' => 'android',
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('isConfigured')->with(PushNotificationService::PROFILE_CHECKOUTNOW)->andReturn(true);
        $push->shouldReceive('sendToTokens')
            ->once()
            ->withArgs(function ($tokens, $title, $body, $data, $channel, $profile) use ($wallet) {
                return $tokens === [['token' => 'fcm-token-wallet-credit', 'platform' => 'android']]
                    && $profile === PushNotificationService::PROFILE_CHECKOUTNOW
                    && $title === 'Money received'
                    && str_contains($body, '₦5,000.00')
                    && str_contains($body, '₦15,000.00')
                    && $data['type'] === 'money_received'
                    && $data['screen'] === 'history'
                    && $data['wallet_id'] === (string) $wallet->id
                    && ($data['credit_source'] ?? '') === 'top_up'
                    && $channel === 'money_received';
            })
            ->andReturn([]);
        $this->app->instance(PushNotificationService::class, $push);

        $this->app->make(ConsumerWalletPushNotificationService::class)
            ->notifyWalletCredited($wallet, 5000, 15000);
    }

    public function test_p2p_received_push_includes_sender_name(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348098765432',
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'balance' => 8000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => '2348098765432',
            'fcm_token' => 'fcm-token-p2p',
            'fcm_platform' => 'ios',
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('isConfigured')->with(PushNotificationService::PROFILE_CHECKOUTNOW)->andReturn(true);
        $push->shouldReceive('sendToTokens')
            ->once()
            ->withArgs(function ($tokens, $title, $body, $data, $channel, $profile) {
                return $tokens === [['token' => 'fcm-token-p2p', 'platform' => 'ios']]
                    && $profile === PushNotificationService::PROFILE_CHECKOUTNOW
                    && str_contains($body, 'Ada sent you ₦2,500.00')
                    && $data['type'] === 'money_received'
                    && $data['screen'] === 'history'
                    && $data['sender_name'] === 'Ada'
                    && ($data['credit_source'] ?? '') === 'p2p_credit';
            })
            ->andReturn([]);
        $this->app->instance(PushNotificationService::class, $push);

        $this->app->make(ConsumerWalletPushNotificationService::class)
            ->notifyP2pReceived($wallet, 2500, 'Ada', 'NGN');
    }

    public function test_skips_push_when_no_fcm_token(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348011111111',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 1000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldNotReceive('sendToTokens');
        $this->app->instance(PushNotificationService::class, $push);

        $this->app->make(ConsumerWalletPushNotificationService::class)
            ->notifyWalletCredited($wallet, 500, 1500);
    }
}
