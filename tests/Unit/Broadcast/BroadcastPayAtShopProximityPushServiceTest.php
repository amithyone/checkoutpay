<?php

namespace Tests\Unit\Broadcast;

use App\Services\Broadcast\BroadcastPayAtShopProximityPushService;
use Tests\TestCase;

class BroadcastPayAtShopProximityPushServiceTest extends TestCase
{
    public function test_build_body_uses_merchant_name(): void
    {
        $service = $this->app->make(BroadcastPayAtShopProximityPushService::class);

        $this->assertSame(
            'MIDAS AGRO is open — tap to pay',
            $service->buildBody('MIDAS AGRO'),
        );
    }

    public function test_build_title_default(): void
    {
        config(['broadcast.pay_at_shop_proximity_push_title' => 'Checkout Nearby Available']);

        $service = $this->app->make(BroadcastPayAtShopProximityPushService::class);

        $this->assertSame('Checkout Nearby Available', $service->buildTitle());
    }

    public function test_looks_like_computer_name(): void
    {
        $service = $this->app->make(BroadcastPayAtShopProximityPushService::class);

        $this->assertTrue($service->looksLikeComputerName('DESKTOP-ABC123'));
        $this->assertTrue($service->looksLikeComputerName('MacBook-Pro.local'));
        $this->assertFalse($service->looksLikeComputerName('MIDAS AGRO'));
        $this->assertFalse($service->looksLikeComputerName('Amithy Store'));
    }
}
