<?php

namespace Tests\Feature;

use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletInactiveReminder;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletInactiveReminderService;
use App\Services\PushNotificationService;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConsumerWalletInactiveReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'consumer_wallet.inactive_reminders_enabled' => true,
            'consumer_wallet.inactive_reminder_timezone' => 'Africa/Lagos',
        ]);
    }

    public function test_sends_push_only_for_inactive_wallet_with_app_token(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348012345678',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 5000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'sender_name' => 'Ada',
        ]);

        ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => '2348012345678',
            'fcm_token' => 'test-fcm-token-abc',
            'fcm_platform' => 'android',
        ]);

        $whatsapp = Mockery::mock(EvolutionWhatsAppClient::class);
        $whatsapp->shouldNotReceive('sendText');
        $this->app->instance(EvolutionWhatsAppClient::class, $whatsapp);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('sendToTokens')
            ->once()
            ->withArgs(function ($tokens, $title, $body, $data, $channel, $profile) {
                return is_array($tokens)
                    && ($tokens[0]['token'] ?? null) === 'test-fcm-token-abc'
                    && $profile === PushNotificationService::PROFILE_CHECKOUTNOW
                    && $channel === 'wallet_alerts'
                    && ($data['type'] ?? null) === 'wallet_inactive_reminder'
                    && str_contains($body, '₦5,000.00');
            })
            ->andReturn([]);
        $this->app->instance(PushNotificationService::class, $push);

        $stats = $this->app->make(ConsumerWalletInactiveReminderService::class)
            ->sendForSlot(WhatsappWalletInactiveReminder::SLOT_MORNING);

        $this->assertSame(1, $stats['wallets']);
        $this->assertSame(1, $stats['push']);
        $this->assertSame(0, $stats['whatsapp']);

        $this->assertDatabaseHas('whatsapp_wallet_inactive_reminders', [
            'whatsapp_wallet_id' => $wallet->id,
            'slot' => WhatsappWalletInactiveReminder::SLOT_MORNING,
            'push_sent' => 1,
            'whatsapp_sent' => 0,
        ]);
    }

    public function test_skips_wallet_without_app_push_token(): void
    {
        WhatsappWallet::query()->create([
            'phone_e164' => '2348011111111',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 5000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        $whatsapp = Mockery::mock(EvolutionWhatsAppClient::class);
        $whatsapp->shouldNotReceive('sendText');
        $this->app->instance(EvolutionWhatsAppClient::class, $whatsapp);

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldNotReceive('sendToTokens');
        $this->app->instance(PushNotificationService::class, $push);

        $stats = $this->app->make(ConsumerWalletInactiveReminderService::class)
            ->sendForSlot(WhatsappWalletInactiveReminder::SLOT_MORNING);

        $this->assertSame(0, $stats['wallets']);
        $this->assertDatabaseCount('whatsapp_wallet_inactive_reminders', 0);
    }

    public function test_skips_wallet_with_transaction_today(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348098765432',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 2500,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => '2348098765432',
            'fcm_token' => 'token-tx-today',
            'fcm_platform' => 'android',
        ]);

        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_P2P_DEBIT,
            'amount' => 100,
        ]);

        $whatsapp = Mockery::mock(EvolutionWhatsAppClient::class);
        $whatsapp->shouldNotReceive('sendText');
        $this->app->instance(EvolutionWhatsAppClient::class, $whatsapp);

        $stats = $this->app->make(ConsumerWalletInactiveReminderService::class)
            ->sendForSlot(WhatsappWalletInactiveReminder::SLOT_EVENING);

        $this->assertSame(0, $stats['wallets']);
        $this->assertDatabaseCount('whatsapp_wallet_inactive_reminders', 0);
    }

    public function test_does_not_send_duplicate_for_same_slot(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348077700001',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 1000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => '2348077700001',
            'fcm_token' => 'token-dup',
            'fcm_platform' => 'ios',
        ]);

        WhatsappWalletInactiveReminder::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'reminder_on' => now('Africa/Lagos')->toDateString(),
            'slot' => WhatsappWalletInactiveReminder::SLOT_MORNING,
            'push_sent' => true,
            'whatsapp_sent' => false,
        ]);

        $whatsapp = Mockery::mock(EvolutionWhatsAppClient::class);
        $whatsapp->shouldNotReceive('sendText');
        $this->app->instance(EvolutionWhatsAppClient::class, $whatsapp);

        $stats = $this->app->make(ConsumerWalletInactiveReminderService::class)
            ->sendForSlot(WhatsappWalletInactiveReminder::SLOT_MORNING);

        $this->assertSame(0, $stats['wallets']);
        $this->assertSame(1, $stats['skipped']);
    }
}
