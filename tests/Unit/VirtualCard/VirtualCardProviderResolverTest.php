<?php

namespace Tests\Unit\VirtualCard;

use App\Models\Setting;
use App\Services\VirtualCard\CashwyreVirtualCardProvider;
use App\Services\VirtualCard\MevonPayVirtualCardProvider;
use App\Services\VirtualCard\VirtualCardProviderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualCardProviderResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_mevonpay(): void
    {
        $resolver = app(VirtualCardProviderResolver::class);
        $this->assertSame(VirtualCardProviderResolver::PROVIDER_MEVONPAY, $resolver->activeKey());
        $this->assertInstanceOf(MevonPayVirtualCardProvider::class, $resolver->active());
    }

    public function test_resolves_cashwyre_when_setting_set(): void
    {
        Setting::set('virtual_card_provider', VirtualCardProviderResolver::PROVIDER_CASHWYRE, 'string', 'virtual_card');
        $resolver = app(VirtualCardProviderResolver::class);
        $this->assertSame(VirtualCardProviderResolver::PROVIDER_CASHWYRE, $resolver->activeKey());
        $this->assertInstanceOf(CashwyreVirtualCardProvider::class, $resolver->active());
    }
}
