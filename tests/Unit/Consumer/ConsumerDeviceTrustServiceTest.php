<?php

namespace Tests\Unit\Consumer;

use App\Models\ConsumerWalletApiAccount;
use App\Services\Consumer\ConsumerDeviceTrustService;
use Tests\TestCase;

class ConsumerDeviceTrustServiceTest extends TestCase
{
    public function test_high_value_transfer_blocked_when_lock_active(): void
    {
        config([
            'consumer_wallet.high_value_single_transfer_cap' => 20000,
        ]);

        $account = new ConsumerWalletApiAccount([
            'transfer_lock_until' => now()->addHour(),
        ]);

        $service = $this->app->make(ConsumerDeviceTrustService::class);

        $this->assertTrue($service->isHighValueTransferBlocked($account, 20001));
        $this->assertFalse($service->isHighValueTransferBlocked($account, 20000));
        $this->assertFalse($service->isHighValueTransferBlocked($account, 5000));
    }

    public function test_transfer_lock_meta_shape(): void
    {
        config([
            'consumer_wallet.high_value_single_transfer_cap' => 20000,
        ]);

        $account = new ConsumerWalletApiAccount([
            'transfer_lock_until' => now()->addHours(6),
            'pin_reset_required' => false,
        ]);

        $service = $this->app->make(ConsumerDeviceTrustService::class);
        $meta = $service->transferLockMeta($account);

        $this->assertSame(20000, $meta['high_value_single_transfer_cap']);
        $this->assertTrue($meta['high_value_transfer_blocked']);
        $this->assertFalse($meta['pin_reset_required']);
        $this->assertNotNull($meta['transfer_lock_until']);
    }

    public function test_requires_step_up_for_existing_user_without_trusted_device(): void
    {
        config([
            'consumer_wallet.device_trust_enabled' => true,
            'consumer_wallet.device_stepup_required_on_login' => true,
            'consumer_wallet.device_first_trust_email_otp' => true,
        ]);

        $service = $this->app->make(ConsumerDeviceTrustService::class);
        $account = new ConsumerWalletApiAccount(['id' => 1]);
        $account->setRelation('trustedDevices', collect());

        $this->assertTrue($service->requiresStepUp($account, 'device-a'));
        $this->assertSame('first_device_email', $service->stepUpMode($account, 'device-a'));
        $this->assertNull($service->stepUpMode($account, null));
    }

    public function test_missing_device_id_allows_legacy_app_login(): void
    {
        config([
            'consumer_wallet.device_trust_enabled' => true,
            'consumer_wallet.device_stepup_required_on_login' => true,
            'consumer_wallet.device_first_trust_email_otp' => true,
        ]);

        $service = $this->app->make(ConsumerDeviceTrustService::class);
        $trusted = new \App\Models\ConsumerTrustedDevice([
            'device_id' => 'phone-one',
            'label' => 'Pixel',
            'kyc_confirmed_at' => now(),
        ]);
        $trusted->setRelation('passkey', null);
        $account = new ConsumerWalletApiAccount(['id' => 3]);
        $account->setRelation('trustedDevices', collect([$trusted]));

        // Old app: no install id → no gate.
        $this->assertNull($service->stepUpMode($account, null));
        $this->assertFalse($service->requiresStepUp($account, null));

        // New app: wrong / matching install id.
        $this->assertSame('device_mismatch', $service->stepUpMode($account, 'phone-two'));
        $this->assertNull($service->stepUpMode($account, 'phone-one'));
    }

    public function test_device_id_present_without_trusted_requires_first_email(): void
    {
        config([
            'consumer_wallet.device_trust_enabled' => true,
            'consumer_wallet.device_stepup_required_on_login' => true,
            'consumer_wallet.device_first_trust_email_otp' => true,
        ]);

        $service = $this->app->make(ConsumerDeviceTrustService::class);
        $account = new ConsumerWalletApiAccount(['id' => 4]);
        $account->setRelation('trustedDevices', collect());

        $this->assertNull($service->stepUpMode($account, null));
        $this->assertSame('first_device_email', $service->stepUpMode($account, 'cn_new_install_abc'));
    }

    public function test_first_device_email_stepup_can_be_disabled(): void
    {
        config([
            'consumer_wallet.device_trust_enabled' => true,
            'consumer_wallet.device_stepup_required_on_login' => true,
            'consumer_wallet.device_first_trust_email_otp' => false,
        ]);

        $service = $this->app->make(ConsumerDeviceTrustService::class);
        $account = new ConsumerWalletApiAccount(['id' => 1]);
        $account->setRelation('trustedDevices', collect());

        $this->assertFalse($service->requiresStepUp($account, 'device-a'));
    }

    public function test_device_mismatch_payload_when_trusted_device_exists(): void
    {
        config([
            'consumer_wallet.device_trust_enabled' => true,
            'consumer_wallet.device_stepup_required_on_login' => true,
        ]);

        $service = $this->app->make(ConsumerDeviceTrustService::class);
        $trusted = new \App\Models\ConsumerTrustedDevice([
            'device_id' => 'phone-one',
            'label' => 'Pixel',
            'kyc_confirmed_at' => now(),
        ]);
        $trusted->setRelation('passkey', null);
        $account = new ConsumerWalletApiAccount(['id' => 2]);
        $account->setRelation('trustedDevices', collect([$trusted]));

        $this->assertTrue($service->requiresStepUp($account, 'phone-two'));
        $this->assertFalse($service->requiresStepUp($account, 'phone-one'));
        $response = $service->deviceMismatchJsonResponse($account, 'phone-two');
        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('device_mismatch', $response->getData(true)['code']);
    }

    public function test_tier1_skips_bvn_requirement(): void
    {
        $service = $this->app->make(ConsumerDeviceTrustService::class);

        $tier1 = new \App\Models\WhatsappWallet(['tier' => 1, 'kyc_bvn' => null, 'kyc_nin' => null]);
        $this->assertFalse($service->bvnRequiredForStepUp($tier1));

        $tier2NoBvn = new \App\Models\WhatsappWallet(['tier' => 2, 'kyc_bvn' => null, 'kyc_nin' => null]);
        $this->assertFalse($service->bvnRequiredForStepUp($tier2NoBvn));

        $tier2WithBvn = new \App\Models\WhatsappWallet(['tier' => 2, 'kyc_bvn' => '12345678901', 'kyc_nin' => null]);
        $this->assertTrue($service->bvnRequiredForStepUp($tier2WithBvn));
    }
}
