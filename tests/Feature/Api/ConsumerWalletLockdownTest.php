<?php

namespace Tests\Feature\Api;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ConsumerWalletLockdownTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '08012345678';

    private const COUNTRY = 'NG';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_anyone_can_lock_with_pin_and_dob(): void
    {
        $wallet = $this->makeWallet();

        $this->postJson('/api/v1/consumer/auth/lockdown', [
            'phone' => self::PHONE,
            'country' => self::COUNTRY,
            'pin' => '1234',
            'dob' => '1990-05-12',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertNotNull($wallet->fresh()->locked_down_at);
    }

    public function test_pin_login_is_blocked_after_lockdown(): void
    {
        $this->makeWallet(['locked_down_at' => now()]);

        $this->postJson('/api/v1/consumer/auth/pin/verify', [
            'phone' => self::PHONE,
            'country' => self::COUNTRY,
            'pin' => '1234',
        ])->assertStatus(423)
            ->assertJsonPath('data.locked_down', true);
    }

    public function test_tier1_unlock_uses_frequent_people_and_skips_bvn(): void
    {
        $wallet = $this->makeWallet(['locked_down_at' => now()]);
        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_P2P_DEBIT,
            'amount' => 1500,
            'counterparty_phone_e164' => '2348098765432',
            'counterparty_account_name' => 'Ada Okafor',
        ]);
        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_P2P_DEBIT,
            'amount' => 800,
            'counterparty_phone_e164' => '2348011111111',
            'counterparty_account_name' => 'Musa Bello',
        ]);

        $start = $this->postJson('/api/v1/consumer/auth/lockdown/unlock/start', [
            'phone' => self::PHONE,
            'country' => self::COUNTRY,
        ])->assertOk();

        $this->assertFalse((bool) $start->json('data.needs_identity'));
        $this->assertSame(2, (int) $start->json('data.people_required'));
        $token = (string) $start->json('data.unlock_token');

        $this->postJson('/api/v1/consumer/auth/lockdown/unlock', [
            'unlock_token' => $token,
            'pin' => '1234',
            'people' => ['Ada', 'Musa'],
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertNull($wallet->fresh()->locked_down_at);
    }

    public function test_tier2_unlock_requires_bvn(): void
    {
        $wallet = $this->makeWallet([
            'locked_down_at' => now(),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'kyc_bvn' => '22222222222',
        ]);

        $start = $this->postJson('/api/v1/consumer/auth/lockdown/unlock/start', [
            'phone' => self::PHONE,
            'country' => self::COUNTRY,
        ])->assertOk();
        $token = (string) $start->json('data.unlock_token');

        $this->postJson('/api/v1/consumer/auth/lockdown/unlock', [
            'unlock_token' => $token,
            'pin' => '1234',
        ])->assertStatus(422);

        $this->postJson('/api/v1/consumer/auth/lockdown/unlock', [
            'unlock_token' => $token,
            'pin' => '1234',
            'bvn' => '22222222222',
        ])->assertOk();

        $this->assertNull($wallet->fresh()->locked_down_at);
    }

    public function test_unlock_email_must_match_before_code(): void
    {
        $this->makeWallet([
            'locked_down_at' => now(),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'kyc_email' => 'owner@example.com',
        ]);

        $start = $this->postJson('/api/v1/consumer/auth/lockdown/unlock/start', [
            'phone' => self::PHONE,
            'country' => self::COUNTRY,
        ])->assertOk()->assertJsonPath('data.needs_email', true);
        $token = (string) $start->json('data.unlock_token');

        $this->postJson('/api/v1/consumer/auth/lockdown/unlock/email/request', [
            'unlock_token' => $token,
            'email' => 'wrong@example.com',
        ])->assertStatus(422);

        $this->postJson('/api/v1/consumer/auth/lockdown/unlock/email/request', [
            'unlock_token' => $token,
            'email' => 'owner@example.com',
        ])->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeWallet(array $overrides = []): WhatsappWallet
    {
        return WhatsappWallet::query()->create(array_merge([
            'phone_e164' => '2348012345678',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 5000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'pin_hash' => Hash::make('1234'),
            'pin_set_at' => now(),
            'kyc_dob' => '1990-05-12',
        ], $overrides));
    }
}
