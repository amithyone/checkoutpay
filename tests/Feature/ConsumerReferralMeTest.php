<?php

namespace Tests\Feature;

use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletReferral;
use App\Models\WhatsappWalletReferralBonus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumerReferralMeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\TouchConsumerAppSession::class);
    }

    public function test_me_returns_flat_fields_expected_by_native_app(): void
    {
        config(['consumer_wallet.referral.enabled' => true]);

        $referrer = WhatsappWallet::query()->create([
            'phone_e164' => '2348011111111',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'pay_code' => 'REF111',
        ]);

        $referred = WhatsappWallet::query()->create([
            'phone_e164' => '2348022222222',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        $referral = WhatsappWalletReferral::query()->create([
            'referrer_wallet_id' => $referrer->id,
            'referred_wallet_id' => $referred->id,
            'attribution_source' => 'register',
            'attributed_at' => now(),
            'bonus_ends_at' => now()->addMonths(6),
            'counted_tx_total' => 0,
            'milestones_paid' => 0,
        ]);

        WhatsappWalletReferralBonus::query()->create([
            'referral_id' => $referral->id,
            'referrer_wallet_id' => $referrer->id,
            'referred_wallet_id' => $referred->id,
            'type' => WhatsappWalletReferralBonus::TYPE_FIRST_DEPOSIT,
            'amount' => 500,
            'currency' => 'NGN',
            'idempotency_key' => 'test:first_deposit:1',
            'meta' => [],
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $referrer->id,
            'phone_e164' => $referrer->phone_e164,
        ]);

        $token = $account->createToken('test');

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/consumer/referrals/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'pay_code',
                    'phone_e164',
                    'referred_count',
                    'active_referred_count',
                    'total_bonus_ngn',
                    'was_referred',
                    'rules' => ['enabled'],
                ],
            ])
            ->assertJsonPath('data.phone_e164', '2348011111111')
            ->assertJsonPath('data.referred_count', 1)
            ->assertJsonPath('data.active_referred_count', 1)
            ->assertJsonPath('data.total_bonus_ngn', 500)
            ->assertJsonPath('data.was_referred', false)
            ->assertJsonPath('data.rules.enabled', true)
            ->assertJsonMissingPath('data.stats');
    }
}
