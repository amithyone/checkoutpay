<?php

namespace Tests\Unit\Whatsapp;

use App\Models\WhatsappWallet;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\WhatsappInboundHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsappAdminBotPauseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'whatsapp.evolution.instance' => 'test-wallet-instance',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_admin_bot_pause_suppresses_auto_replies(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345678',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'admin_bot_paused' => true,
        ]);

        $mock = Mockery::mock(EvolutionWhatsAppClient::class);
        $mock->shouldNotReceive('sendText');
        $this->app->instance(EvolutionWhatsAppClient::class, $mock);

        app(WhatsappInboundHandler::class)->handleConsumerAppTurn($wallet->phone_e164, 'hello');

        $this->assertTrue($wallet->fresh()->isAdminBotPaused());
    }

    public function test_start_bot_resumes_admin_paused_bot(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348098765432',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'admin_bot_paused' => true,
        ]);

        $mock = Mockery::mock(EvolutionWhatsAppClient::class);
        $mock->shouldReceive('sendText')
            ->once()
            ->with('test-wallet-instance', $wallet->phone_e164, Mockery::type('string'));
        $this->app->instance(EvolutionWhatsAppClient::class, $mock);

        app(WhatsappInboundHandler::class)->handleConsumerAppTurn($wallet->phone_e164, 'START BOT');

        $this->assertFalse($wallet->fresh()->isAdminBotPaused());
    }
}
