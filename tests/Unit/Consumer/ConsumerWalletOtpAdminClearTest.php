<?php

namespace Tests\Unit\Consumer;

use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConsumerWalletOtpAdminClearTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_clear_resets_app_and_whatsapp_otp_lockouts(): void
    {
        config([
            'consumer_wallet.otp_max_unused_sends' => 3,
            'consumer_wallet.otp_max_attempts' => 5,
            'whatsapp.otp.max_attempts' => 5,
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348148790554',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        Cache::put('consumer_wallet_otp_unused_sends:'.hash('sha256', $wallet->phone_e164), 3, 3600);
        Cache::put('consumer_wallet_otp_attempts:'.hash('sha256', $wallet->phone_e164), 5, 3600);
        Cache::put('consumer_wallet_otp:'.hash('sha256', $wallet->phone_e164), ['code_hash' => 'x'], 600);

        WhatsappSession::query()->create([
            'phone_e164' => $wallet->phone_e164,
            'remote_jid' => $wallet->phone_e164.'@s.whatsapp.net',
            'evolution_instance' => 'Rentals',
            'state' => WhatsappSession::STATE_AWAIT_OTP,
            'otp_attempts' => 5,
        ]);

        $service = app(ConsumerWalletOtpService::class);

        $this->assertTrue($service->lockoutStatusForAdmin($wallet->phone_e164)['is_stuck']);

        $result = $service->clearAllLockouts($wallet->phone_e164);

        $this->assertTrue($result['cleared_unused_sends']);
        $this->assertTrue($result['cleared_verify_attempts']);
        $this->assertTrue($result['cleared_pending_otp']);
        $this->assertTrue($result['cleared_whatsapp_session_otp']);

        $after = $service->lockoutStatusForAdmin($wallet->phone_e164);
        $this->assertFalse($after['is_stuck']);
        $this->assertSame(0, WhatsappSession::query()->where('phone_e164', $wallet->phone_e164)->value('otp_attempts'));
    }
}
