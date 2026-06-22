<?php

namespace Tests\Feature;

use App\Models\ConsumerAppSession;
use App\Models\WhatsappWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConsumerAppSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pin_login_creates_app_session_and_logout_ends_it(): void
    {
        WhatsappWallet::query()->create([
            'phone_e164' => '2348012345678',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'pin_hash' => Hash::make('1234'),
            'pin_set_at' => now(),
        ]);

        $login = $this->postJson('/api/v1/consumer/auth/pin/verify', [
            'phone' => '08012345678',
            'pin' => '1234',
            'client_context' => ['platform' => 'ios', 'app_version' => '1.0.0'],
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'app_session_id']]);

        $sessionUuid = (string) $login->json('data.app_session_id');
        $this->assertNotEmpty($sessionUuid);

        $session = ConsumerAppSession::query()->where('session_uuid', $sessionUuid)->first();
        $this->assertNotNull($session);
        $this->assertSame('pin', $session->login_method);
        $this->assertSame('ios', $session->platform);
        $this->assertNull($session->ended_at);

        $token = (string) $login->json('data.token');

        $this->postJson('/api/v1/consumer/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
            'X-App-Session-Id' => $sessionUuid,
        ])->assertOk();

        $session->refresh();
        $this->assertNotNull($session->ended_at);
    }
}
