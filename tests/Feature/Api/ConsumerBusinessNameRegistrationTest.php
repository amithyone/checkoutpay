<?php

namespace Tests\Feature\Api;

use App\Models\BusinessNameRegistration;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\BusinessNameRegistrationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerBusinessNameRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'consumer_wallet.business_name_registration.enabled' => true,
            'consumer_wallet.business_name_registration.fee_amount' => 15000,
            'consumer_wallet.business_name_registration.fee_currency' => 'NGN',
        ]);
    }

    public function test_get_returns_coming_soon_when_feature_disabled(): void
    {
        config(['consumer_wallet.business_name_registration.enabled' => false]);

        [, $account] = $this->walletAccount();
        Sanctum::actingAs($account, ['consumer']);

        $response = $this->getJson('/api/v1/consumer/business-name-registration');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.config.available', false)
            ->assertJsonPath('data.requests', [])
            ->assertJsonPath('data.business_account', null);
    }

    public function test_get_returns_live_config_when_enabled(): void
    {
        [, $account] = $this->walletAccount();
        Sanctum::actingAs($account, ['consumer']);

        $response = $this->getJson('/api/v1/consumer/business-name-registration');

        $response->assertOk()
            ->assertJsonPath('data.config.available', true)
            ->assertJsonPath('data.config.fee_amount', 15000)
            ->assertJsonPath('data.config.fee_currency', 'NGN');
    }

    public function test_post_submits_registration_and_debits_wallet(): void
    {
        [$wallet, $account] = $this->walletAccount();
        Sanctum::actingAs($account, ['consumer']);

        $before = (float) $wallet->balance;

        $response = $this->post('/api/v1/consumer/business-name-registration', [
            'proposed_name' => 'Acme Ventures Ltd',
            'alternate_name' => 'Acme Trading',
            'owner_full_name' => 'Jane Doe',
            'owner_phone' => '+2348012345678',
            'owner_email' => 'jane@example.com',
            'business_address' => '12 Lagos Street, Lagos',
            'nature_of_business' => 'Retail trade',
            'id_type' => 'nin',
            'pin' => '2468',
            'id_document' => UploadedFile::fake()->image('nin.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', BusinessNameRegistration::STATUS_PAID)
            ->assertJsonPath('data.progress_percent', 15)
            ->assertJsonPath('data.fee_amount', 15000);

        $wallet->refresh();
        $this->assertSame($before - 15000, (float) $wallet->balance);

        $this->assertDatabaseHas('business_name_registrations', [
            'whatsapp_wallet_id' => $wallet->id,
            'proposed_name' => 'Acme Ventures Ltd',
            'status' => BusinessNameRegistration::STATUS_PAID,
        ]);

        $this->assertDatabaseHas('whatsapp_wallet_transactions', [
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_BUSINESS_NAME_REGISTRATION_FEE,
            'amount' => -15000,
        ]);
    }

    public function test_post_rejects_invalid_pin(): void
    {
        [, $account] = $this->walletAccount();
        Sanctum::actingAs($account, ['consumer']);

        $response = $this->post('/api/v1/consumer/business-name-registration', [
            'proposed_name' => 'Brightline Services',
            'owner_full_name' => 'Jane Doe',
            'owner_phone' => '+2348012345678',
            'owner_email' => 'jane@example.com',
            'business_address' => 'Lagos',
            'nature_of_business' => 'Services',
            'id_type' => 'passport',
            'pin' => '9999',
            'id_document' => UploadedFile::fake()->image('passport.png'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid PIN');
    }

    public function test_approval_sets_business_pay_in_on_wallet(): void
    {
        [$wallet, $account] = $this->walletAccount();

        $registration = BusinessNameRegistration::query()->create([
            'public_id' => 'bnr_test001',
            'whatsapp_wallet_id' => $wallet->id,
            'reference' => 'BNR-2026-00001',
            'proposed_name' => 'Acme Ventures Ltd',
            'owner_full_name' => 'Jane Doe',
            'owner_phone' => '+2348012345678',
            'owner_email' => 'jane@example.com',
            'business_address' => 'Lagos',
            'nature_of_business' => 'Retail',
            'id_type' => 'nin',
            'id_document_path' => 'business-name-registrations/1/1/nin.jpg',
            'status' => BusinessNameRegistration::STATUS_UNDER_REVIEW,
            'progress_percent' => 65,
            'fee_amount' => 15000,
            'fee_currency' => 'NGN',
            'submitted_at' => now(),
        ]);

        $result = app(BusinessNameRegistrationWorkflowService::class)->updateStatus(
            $registration,
            BusinessNameRegistration::STATUS_APPROVED,
            [
                'approved_business_name' => 'Acme Ventures Ltd',
                'business_account_number' => '9876543210',
                'business_account_name' => 'Acme Ventures Ltd',
                'business_bank_name' => 'Rubies MFB',
                'business_bank_code' => '090175',
            ],
        );

        $this->assertTrue($result['ok']);

        $wallet->refresh();
        $this->assertSame('9876543210', $wallet->business_pay_in_account_number);
        $this->assertSame($registration->id, (int) $wallet->active_business_name_registration_id);

        Sanctum::actingAs($account, ['consumer']);
        $response = $this->getJson('/api/v1/consumer/wallet');
        $response->assertOk()
            ->assertJsonPath('data.business_pay_in.account_number', '9876543210')
            ->assertJsonPath('data.business_pay_in.account_name', 'Acme Ventures Ltd');
    }

    /**
     * @return array{0: WhatsappWallet, 1: ConsumerWalletApiAccount}
     */
    private function walletAccount(): array
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345678',
            'balance' => 50000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'sender_name' => 'Jane',
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        return [$wallet, $account];
    }
}
