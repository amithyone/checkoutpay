<?php

namespace Tests\Feature\Api;

use App\Models\BusinessAccountApplication;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Consumer\BusinessAccountOnboardingWorkflowService;
use App\Services\Consumer\BusinessKybComplianceService;
use App\Services\Consumer\WalletKycComplianceService;
use App\Services\MevonPay\MevonIdentityVerificationService;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ConsumerKycFailClosedTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+2348087654321';

    private const BVN = '22184739201';

    private const NIN = '11284739201';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'consumer_wallet.kyc_fail_closed' => true,
            'consumer_wallet.business_account_onboarding.enabled' => true,
            'consumer_wallet.business_account_onboarding.fee_amount' => 0,
            'whatsapp.evolution.instance' => 'test-instance',
        ]);
        Mail::fake();
        Storage::fake('local');

        $wa = Mockery::mock(EvolutionWhatsAppClient::class);
        $wa->shouldReceive('sendAuthenticationOtp')->andReturn(false);
        $wa->shouldReceive('sendText')->andReturn(true);
        $this->app->instance(EvolutionWhatsAppClient::class, $wa);

        $identity = Mockery::mock(MevonIdentityVerificationService::class);
        $identity->shouldReceive('verifyPersonal')->andReturn([
            'ok' => true,
            'message' => 'Identity verified via Mevon.',
            'full_name' => 'Adaeze Okonkwo',
            'dob' => '1990-05-15',
            'reference' => 'ref-1',
            'raw' => [],
        ]);
        $this->app->instance(MevonIdentityVerificationService::class, $identity);
    }

    public function test_register_without_bvn_or_nin_fails_closed(): void
    {
        $this->seedOtp('112233');

        $this->postJson('/api/v1/consumer/auth/register', [
            'phone' => self::PHONE,
            'code' => '112233',
            'fname' => 'Adaeze',
            'lname' => 'Okonkwo',
            'email' => 'ada.okonkwo@gmail.com',
            'dob' => '1990-05-15',
            'gender' => 'female',
        ])->assertStatus(422)->assertJsonPath('error_code', 'kyc_incomplete');
    }

    public function test_register_verifies_identity_and_restricts_until_email(): void
    {
        $this->seedOtp('112233');

        $res = $this->postJson('/api/v1/consumer/auth/register', $this->registerPayload());
        $res->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($res->json('data.token'));
        $this->assertSame(1, (int) $res->json('data.kyc.kyc_tier'));

        $wallet = WhatsappWallet::query()->where('phone_e164', '2348087654321')->first();
        $this->assertNotNull($wallet);
        $this->assertSame(1, (int) $wallet->kyc_tier);
        $wallet->balance = 1000;
        $wallet->save();
        $this->assertFalse(app(WalletKycComplianceService::class)->canTransact($wallet));
        $this->assertFalse($wallet->fresh()->canDebit(100)['ok']);
        $this->assertSame('account_restricted', $wallet->fresh()->canDebit(100)['error_code'] ?? null);
    }

    public function test_duplicate_bvn_returns_409(): void
    {
        WhatsappWallet::query()->create([
            'phone_e164' => '2348098765432',
            'kyc_bvn' => self::BVN,
            'kyc_fname' => 'Other',
            'kyc_lname' => 'Person',
            'kyc_email' => 'other.person@gmail.com',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'balance' => 0,
        ]);

        $this->seedOtp('112233');
        $this->postJson('/api/v1/consumer/auth/register', $this->registerPayload())
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'duplicate_identity');
    }

    public function test_identity_mismatch_reviews_and_blocks_debit(): void
    {
        $identity = Mockery::mock(MevonIdentityVerificationService::class);
        $identity->shouldReceive('verifyPersonal')->andReturn([
            'ok' => false,
            'message' => 'Name mismatch',
            'full_name' => 'Someone Else',
            'reference' => 'x',
            'raw' => [],
        ]);
        $this->app->instance(MevonIdentityVerificationService::class, $identity);

        $this->seedOtp('112233');
        $this->postJson('/api/v1/consumer/auth/register', $this->registerPayload())->assertOk();

        $wallet = WhatsappWallet::query()->where('phone_e164', '2348087654321')->first();
        $this->assertSame(WhatsappWallet::KYC_STATUS_REVIEW, $wallet->kyc_status);
        $this->assertFalse($wallet->canDebit(10)['ok']);
    }

    public function test_kyc_tier_caps_and_upgrade_after_both_ids(): void
    {
        $wallet = $this->verifiedTier1Wallet();
        $wallet->balance = 300000;
        $wallet->save();

        $this->assertTrue($wallet->canDebit(50000)['ok']);
        $this->assertFalse($wallet->fresh()->canDebit(50000.01)['ok']);

        $this->actingAsWallet($wallet);
        $out = app(WalletKycComplianceService::class)->submitIdentity($wallet->fresh(), [
            'nin' => self::NIN,
            'fname' => 'Adaeze',
            'lname' => 'Okonkwo',
            'dob' => '1990-05-15',
            'gender' => 'female',
            'email' => 'ada.okonkwo@gmail.com',
        ]);
        $this->assertTrue($out['ok'], $out['message'] ?? '');
        $wallet = $wallet->fresh();
        $this->assertNotEmpty($wallet->kyc_nin);
        $this->assertNotNull($wallet->kyc_nin_verified_at);

        $file = UploadedFile::fake()->image('id.jpg');
        $this->post('/api/v1/consumer/kyc/id-document', [
            'id_type' => 'passport',
            'document' => $file,
        ], ['Accept' => 'application/json'])->assertOk();

        $wallet = $wallet->fresh();
        $this->assertSame(1, (int) $wallet->kyc_tier);
        app(WalletKycComplianceService::class)->acceptIdDocument($wallet);
        $wallet = $wallet->fresh();
        $this->assertSame(2, (int) $wallet->kyc_tier);
        $this->assertTrue($wallet->canDebit(200000)['ok']);
        $this->assertFalse($wallet->canDebit(200000.01)['ok']);
    }

    public function test_business_without_ubo_cannot_be_approved(): void
    {
        $wallet = $this->verifiedTier1Wallet();
        $wallet->pin_hash = Hash::make('2468');
        $wallet->save();
        $this->actingAsWallet($wallet);

        $file = UploadedFile::fake()->create('cac.pdf', 100, 'application/pdf');
        $this->post('/api/v1/consumer/business-account/onboarding', [
            'account_plan' => 'payments_only',
            'business_name' => 'Ada Ventures Limited',
            'email' => 'adaventures@gmail.com',
            'address' => '12 Marina, Lagos',
            'pin' => '2468',
            'cac_number' => 'RC123456',
            'cac_document' => $file,
            'actual_activity' => 'Payments',
            'sector' => 'fintech',
            'source_of_funds' => 'Revenue',
            'registered_address' => '12 Marina, Lagos',
        ], ['Accept' => 'application/json'])->assertOk();

        $application = BusinessAccountApplication::query()->first();
        $this->assertSame(BusinessAccountApplication::STATUS_DRAFT, $application->status);

        $this->postJson('/api/v1/consumer/business-account/onboarding/submit')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ubo_not_established');

        $result = app(BusinessAccountOnboardingWorkflowService::class)->updateStatus(
            $application->fresh(),
            BusinessAccountApplication::STATUS_AWAITING_PASSWORD,
        );
        $this->assertFalse($result['ok']);
    }

    public function test_business_daily_limit_enforced_when_verified(): void
    {
        $wallet = $this->verifiedTier1Wallet();
        $wallet->business_balance = 20_000_000;
        $wallet->save();

        $app = BusinessAccountApplication::query()->create([
            'public_id' => 'baa_test',
            'whatsapp_wallet_id' => $wallet->id,
            'reference' => 'BAA-2026-00001',
            'account_plan' => BusinessAccountApplication::PLAN_PAYMENTS_ONLY,
            'business_name' => 'Ada Ventures Limited',
            'email' => 'adaventures@gmail.com',
            'address' => 'Lagos',
            'status' => BusinessAccountApplication::STATUS_ACTIVE,
            'kyb_status' => 'verified',
            'daily_limit_ngn' => 10_000_000,
            'cac_document_path' => 'x',
        ]);
        $wallet->active_business_account_application_id = $app->id;
        $wallet->save();

        $this->assertTrue($wallet->fresh()->canDebitBusiness(10_000_000)['ok']);
        $this->assertFalse($wallet->fresh()->canDebitBusiness(10_000_000.01)['ok']);
    }

    /**
     * @return array<string, string>
     */
    private function registerPayload(): array
    {
        return [
            'phone' => self::PHONE,
            'code' => '112233',
            'fname' => 'Adaeze',
            'lname' => 'Okonkwo',
            'email' => 'ada.okonkwo@gmail.com',
            'dob' => '1990-05-15',
            'gender' => 'female',
            'bvn' => self::BVN,
        ];
    }

    private function seedOtp(string $code): void
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits(self::PHONE);
        Cache::put('consumer_wallet_otp:'.hash('sha256', (string) $e164), [
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ], 600);
    }

    private function verifiedTier1Wallet(): WhatsappWallet
    {
        return WhatsappWallet::query()->create([
            'phone_e164' => '2348087654321',
            'balance' => 50000,
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'kyc_fname' => 'Adaeze',
            'kyc_lname' => 'Okonkwo',
            'kyc_email' => 'ada.okonkwo@gmail.com',
            'kyc_dob' => '1990-05-15',
            'kyc_gender' => 'female',
            'kyc_bvn' => self::BVN,
            'kyc_bvn_verified_at' => now(),
            'kyc_email_verified_at' => now(),
            'kyc_status' => WhatsappWallet::KYC_STATUS_VERIFIED,
            'kyc_tier' => 1,
            'pin_hash' => Hash::make('2468'),
        ]);
    }

    private function actingAsWallet(WhatsappWallet $wallet): ConsumerWalletApiAccount
    {
        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);
        Sanctum::actingAs($account, ['consumer']);

        return $account;
    }
}
