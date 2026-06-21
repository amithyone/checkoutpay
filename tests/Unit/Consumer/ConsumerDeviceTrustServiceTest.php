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
            'consumer_wallet.high_value_single_transfer_cap' => 10000,
        ]);

        $account = new ConsumerWalletApiAccount([
            'transfer_lock_until' => now()->addHour(),
        ]);

        $service = $this->app->make(ConsumerDeviceTrustService::class);

        $this->assertTrue($service->isHighValueTransferBlocked($account, 10001));
        $this->assertFalse($service->isHighValueTransferBlocked($account, 10000));
        $this->assertFalse($service->isHighValueTransferBlocked($account, 5000));
    }

    public function test_transfer_lock_meta_shape(): void
    {
        config([
            'consumer_wallet.high_value_single_transfer_cap' => 10000,
        ]);

        $account = new ConsumerWalletApiAccount([
            'transfer_lock_until' => now()->addHours(6),
        ]);

        $service = $this->app->make(ConsumerDeviceTrustService::class);
        $meta = $service->transferLockMeta($account);

        $this->assertSame(10000, $meta['high_value_single_transfer_cap']);
        $this->assertTrue($meta['high_value_transfer_blocked']);
        $this->assertNotNull($meta['transfer_lock_until']);
    }
}
