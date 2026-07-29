<?php

namespace Tests\Unit;

use App\Models\WhatsappWallet;
use App\Services\Consumer\WalletReferralLaunchNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WalletReferralLaunchNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['consumer_wallet.referral.enabled' => true]);
    }

    public function test_dry_run_does_not_mark_wallet_notified(): void
    {
        Mail::fake();

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348012345678',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'kyc_email' => 'user@example.com',
            'referral_launch_notified_at' => null,
        ]);

        $service = app(WalletReferralLaunchNotificationService::class);
        $counts = $service->sendAll(true, false, true, false);

        $this->assertSame(0, $counts['marked_notified']);
        $this->assertNull($wallet->fresh()->referral_launch_notified_at);
        Mail::assertNothingSent();
    }

    public function test_already_notified_wallet_has_timestamp(): void
    {
        $notifiedAt = now()->startOfSecond();

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348098765432',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'kyc_email' => 'done@example.com',
            'referral_launch_notified_at' => $notifiedAt,
        ]);

        $this->assertNotNull($wallet->fresh()->referral_launch_notified_at);
    }
}
