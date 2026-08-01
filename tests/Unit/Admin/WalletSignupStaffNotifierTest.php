<?php

namespace Tests\Unit\Admin;

use App\Models\Admin;
use App\Models\WhatsappWallet;
use App\Services\Admin\WalletSignupStaffNotifier;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WalletSignupStaffNotifierTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_notifies_wallet_support_with_phone_and_toggle_on(): void
    {
        config(['whatsapp.proactive_notifications_enabled' => true]);

        Admin::query()->create([
            'name' => 'Desk',
            'email' => 'desk@example.com',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_WALLET_SUPPORT,
            'is_active' => true,
            'whatsapp_e164' => '2348012345678',
            'notify_wallet_signup' => true,
        ]);

        Admin::query()->create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_ADMIN,
            'is_active' => true,
            'whatsapp_e164' => '2348099999999',
            'notify_wallet_signup' => true,
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348087654321',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'sender_name' => 'Ada Lovelace',
            'pin_hash' => bcrypt('1234'),
            'pin_set_at' => now(),
        ]);

        $client = Mockery::mock(EvolutionWhatsAppClient::class);
        $client->shouldReceive('sendText')->once()->withArgs(function ($instance, $phone, $text) {
            return $phone === '2348012345678' && str_contains($text, 'New wallet signup');
        });
        $this->app->instance(EvolutionWhatsAppClient::class, $client);

        app(WalletSignupStaffNotifier::class)->notifyIfFirstComplete($wallet);

        $this->assertNotNull($wallet->fresh()->wallet_signup_notified_at);
    }

    public function test_skips_when_toggle_off(): void
    {
        config(['whatsapp.proactive_notifications_enabled' => true]);

        Admin::query()->create([
            'name' => 'Desk',
            'email' => 'desk2@example.com',
            'password' => bcrypt('password'),
            'role' => Admin::ROLE_WALLET_SUPPORT,
            'is_active' => true,
            'whatsapp_e164' => '2348012345678',
            'notify_wallet_signup' => false,
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348087654322',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'sender_name' => 'Ada',
            'pin_hash' => bcrypt('1234'),
            'pin_set_at' => now(),
        ]);

        $client = Mockery::mock(EvolutionWhatsAppClient::class);
        $client->shouldNotReceive('sendText');
        $this->app->instance(EvolutionWhatsAppClient::class, $client);

        app(WalletSignupStaffNotifier::class)->notifyIfFirstComplete($wallet);

        $this->assertNull($wallet->fresh()->wallet_signup_notified_at);
    }
}
