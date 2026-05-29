<?php

namespace Tests\Unit\Whatsapp;

use App\Models\WhatsappWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappWalletTier1LimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_credit_does_not_increment_daily_send_total(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348011111111',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'daily_transfer_total' => 0,
            'daily_transfer_for_date' => now()->toDateString(),
        ]);

        $this->assertTrue($wallet->canCredit(49_000)['ok']);
        $wallet->balance = 49_000;
        $wallet->save();

        $wallet->refresh();
        $this->assertSame(0.0, (float) $wallet->daily_transfer_total);
        $this->assertTrue($wallet->canDebit(10_000)['ok']);
    }

    public function test_daily_send_limit_only_counts_outbound_and_resets_next_day(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348022222222',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 100_000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'daily_transfer_total' => 45_000,
            'daily_transfer_for_date' => now()->toDateString(),
        ]);

        $this->assertFalse($wallet->canDebit(10_000)['ok']);
        $this->assertStringContainsString('received money does not count', (string) $wallet->canDebit(10_000)['message']);

        $wallet->daily_transfer_for_date = now()->subDay()->toDateString();
        $wallet->save();
        $wallet->refresh();

        $this->assertTrue($wallet->canDebit(10_000)['ok']);
        $this->assertSame(0.0, (float) $wallet->fresh()->daily_transfer_total);
    }

    public function test_tier1_daily_out_remaining_reflects_outbound_only(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348033333333',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 50_000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'daily_transfer_total' => 20_000,
            'daily_transfer_for_date' => now()->toDateString(),
        ]);

        $this->assertEqualsWithDelta(30_000, $wallet->tier1DailyOutRemaining(), 0.01);
        $this->assertEqualsWithDelta(50_000, $wallet->tier1DailyOutLimit(), 0.01);
    }
}
