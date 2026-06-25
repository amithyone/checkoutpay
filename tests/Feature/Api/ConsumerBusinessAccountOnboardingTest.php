<?php

namespace Tests\Feature\Api;

use App\Models\BusinessAccountApplication;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\BusinessAccountOnboardingWorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerBusinessAccountOnboardingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'consumer_wallet.business_account_onboarding.enabled' => true,
            'consumer_wallet.business_account_onboarding.fee_amount' => 0,
            'consumer_wallet.business_account_onboarding.fee_currency' => 'NGN',
        ]);
    }

    public function test_get_returns_coming_soon_when_feature_disabled(): void
    {
        config(['consumer_wallet.business_account_onboarding.enabled' => false]);

        [, $account] = $this->walletAccount();
        Sanctum::actingAs($account, ['consumer']);

        $response = $this->getJson('/api/v1/consumer/business-account/onboarding');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.config.available', false)
            ->assertJsonPath('data.applications', [])
            ->assertJsonPath('data.linked_business', null);
    }

    public function test_get_returns_live_config_when_enabled(): void
    {
        [, $account] = $this->walletAccount();
        Sanctum::actingAs($account, ['consumer']);

        $response = $this->getJson('/api/v1/consumer/business-account/onboarding');

        $response->assertOk()
            ->assertJsonPath('data.config.available', true)
            ->assertJsonPath('data.config.fee_amount', 0)
            ->assertJsonPath('data.can_apply', true);
    }

    public function test_post_submits_application_without_fee(): void
    {
        [$wallet, $account] = $this->walletAccount();
        Sanctum::actingAs($account, ['consumer']);

        $before = (float) $wallet->balance;

        $response = $this->postJson('/api/v1/consumer/business-account/onboarding', [
            'account_plan' => BusinessAccountApplication::PLAN_PAYMENTS_ONLY,
            'service_categories' => ['payments'],
            'business_name' => 'Acme Ventures Ltd',
            'email' => 'acme@example.com',
            'phone' => '+2348012345678',
            'address' => '12 Lagos Street, Lagos',
            'pin' => '2468',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', BusinessAccountApplication::STATUS_SUBMITTED)
            ->assertJsonPath('data.progress_percent', 20)
            ->assertJsonPath('data.fee_amount', 0);

        $wallet->refresh();
        $this->assertSame($before, (float) $wallet->balance);

        $this->assertDatabaseHas('business_account_applications', [
            'whatsapp_wallet_id' => $wallet->id,
            'business_name' => 'Acme Ventures Ltd',
            'status' => BusinessAccountApplication::STATUS_SUBMITTED,
        ]);
    }

    public function test_post_rejects_duplicate_active_application(): void
    {
        [$wallet, $account] = $this->walletAccount();

        BusinessAccountApplication::query()->create([
            'public_id' => 'baa_test001',
            'whatsapp_wallet_id' => $wallet->id,
            'reference' => 'BAA-2026-00001',
            'account_plan' => BusinessAccountApplication::PLAN_PAYMENTS_ONLY,
            'service_categories' => ['payments'],
            'business_name' => 'Existing Co',
            'email' => 'existing@example.com',
            'address' => 'Lagos',
            'status' => BusinessAccountApplication::STATUS_SUBMITTED,
            'progress_percent' => 20,
            'fee_amount' => 0,
            'fee_currency' => 'NGN',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($account, ['consumer']);

        $response = $this->postJson('/api/v1/consumer/business-account/onboarding', [
            'account_plan' => BusinessAccountApplication::PLAN_PAYMENTS_ONLY,
            'business_name' => 'Another Co',
            'email' => 'another@example.com',
            'address' => 'Abuja',
            'pin' => '2468',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_post_rejects_invalid_pin(): void
    {
        [, $account] = $this->walletAccount();
        Sanctum::actingAs($account, ['consumer']);

        $response = $this->postJson('/api/v1/consumer/business-account/onboarding', [
            'account_plan' => BusinessAccountApplication::PLAN_PAYMENTS_ONLY,
            'business_name' => 'Brightline Services',
            'email' => 'bright@example.com',
            'address' => 'Lagos',
            'pin' => '9999',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid PIN');
    }

    public function test_post_debits_wallet_when_fee_configured(): void
    {
        config(['consumer_wallet.business_account_onboarding.fee_amount' => 5000]);

        [$wallet, $account] = $this->walletAccount();
        Sanctum::actingAs($account, ['consumer']);

        $before = (float) $wallet->balance;

        $response = $this->postJson('/api/v1/consumer/business-account/onboarding', [
            'account_plan' => BusinessAccountApplication::PLAN_PAYMENTS_ONLY,
            'business_name' => 'Fee Test Ltd',
            'email' => 'fee@example.com',
            'address' => 'Lagos',
            'pin' => '2468',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.fee_amount', 5000);

        $wallet->refresh();
        $this->assertSame($before - 5000, (float) $wallet->balance);

        $this->assertDatabaseHas('whatsapp_wallet_transactions', [
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_BUSINESS_ACCOUNT_ONBOARDING_FEE,
            'amount' => -5000,
        ]);
    }

    public function test_password_endpoint_after_admin_approval(): void
    {
        [$wallet, $account] = $this->walletAccount();

        $application = BusinessAccountApplication::query()->create([
            'public_id' => 'baa_test002',
            'whatsapp_wallet_id' => $wallet->id,
            'reference' => 'BAA-2026-00002',
            'account_plan' => BusinessAccountApplication::PLAN_PAYMENTS_ONLY,
            'service_categories' => ['payments'],
            'business_name' => 'Approved Co',
            'email' => 'approved@example.com',
            'address' => 'Lagos',
            'status' => BusinessAccountApplication::STATUS_SUBMITTED,
            'progress_percent' => 20,
            'fee_amount' => 0,
            'fee_currency' => 'NGN',
            'submitted_at' => now(),
        ]);

        $result = app(BusinessAccountOnboardingWorkflowService::class)->updateStatus(
            $application,
            BusinessAccountApplication::STATUS_AWAITING_PASSWORD,
        );

        $this->assertTrue($result['ok']);

        $application->refresh();
        $wallet->refresh();

        $this->assertSame(BusinessAccountApplication::STATUS_AWAITING_PASSWORD, $application->status);
        $this->assertNotNull($application->linked_business_id);
        $this->assertSame((int) $application->linked_business_id, (int) $wallet->linked_business_id);

        Sanctum::actingAs($account, ['consumer']);

        $response = $this->postJson('/api/v1/consumer/business-account/onboarding/password', [
            'password' => 'SecurePass1',
            'password_confirmation' => 'SecurePass1',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.dashboard_login_email', 'approved@example.com')
            ->assertJsonPath('data.application.status', BusinessAccountApplication::STATUS_ACTIVE);

        $application->refresh();
        $this->assertSame(BusinessAccountApplication::STATUS_ACTIVE, $application->status);
        $this->assertNotNull($application->password_set_at);
    }

    /**
     * @return array{0: WhatsappWallet, 1: ConsumerWalletApiAccount}
     */
    private function walletAccount(): array
    {
        $phone = '+2348012'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => $phone,
            'balance' => 50000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'sender_name' => 'Jane',
        ]);

        ConsumerWalletApiAccount::query()->where('whatsapp_wallet_id', $wallet->id)->delete();

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        return [$wallet, $account];
    }
}
