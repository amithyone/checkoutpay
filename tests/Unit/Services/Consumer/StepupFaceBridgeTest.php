<?php

namespace Tests\Unit\Services\Consumer;

use App\Services\Consumer\StepupFaceBridge;
use Tests\TestCase;

class StepupFaceBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'checkface.stepup_bridge_enabled' => true,
            'checkface.stepup_bridge_secret' => 'test-bridge-secret-32chars-minimum!!',
            'checkface.app_face_api_base' => 'https://check-outnow.com',
        ]);
    }

    public function test_continue_token_round_trip(): void
    {
        $bridge = app(StepupFaceBridge::class);
        $session = new \App\Models\ConsumerDeviceStepupSession([
            'session_token' => 'sess_abc',
            'consumer_wallet_api_account_id' => 9,
            'phone_e164' => '2348012345678',
            'pending_device_id' => 'dev_1',
        ]);
        $wallet = new \App\Models\WhatsappWallet;
        $wallet->id = 42;

        $token = $bridge->mintContinueToken($session, $wallet);
        $this->assertNotNull($token);

        $claims = $bridge->verifyContinueToken($token, 'sess_abc');
        $this->assertIsArray($claims);
        $this->assertSame('w42', $claims['checkface_user_id']);
        $this->assertSame('face_continue', $claims['purpose']);
    }

    public function test_continue_token_rejects_wrong_session(): void
    {
        $bridge = app(StepupFaceBridge::class);
        $session = new \App\Models\ConsumerDeviceStepupSession([
            'session_token' => 'sess_abc',
            'consumer_wallet_api_account_id' => 9,
            'phone_e164' => '2348012345678',
        ]);
        $wallet = new \App\Models\WhatsappWallet;
        $wallet->id = 42;

        $token = $bridge->mintContinueToken($session, $wallet);
        $this->assertNull($bridge->verifyContinueToken($token, 'sess_other'));
    }
}
