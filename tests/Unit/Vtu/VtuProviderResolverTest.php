<?php

namespace Tests\Unit\Vtu;

use App\Models\Setting;
use App\Services\Vtu\MevonPayVtuProvider;
use App\Services\Vtu\VtuNgProvider;
use App\Services\Vtu\VtuProviderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VtuProviderResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_vtu_ng(): void
    {
        $resolver = app(VtuProviderResolver::class);
        $this->assertSame(VtuProviderResolver::PROVIDER_VTU_NG, $resolver->activeKey());
        $this->assertInstanceOf(VtuNgProvider::class, $resolver->active());
    }

    public function test_resolves_mevonpay_when_setting_set(): void
    {
        Setting::set('vtu_provider', VtuProviderResolver::PROVIDER_MEVONPAY, 'string', 'vtu');
        $resolver = app(VtuProviderResolver::class);
        $this->assertSame(VtuProviderResolver::PROVIDER_MEVONPAY, $resolver->activeKey());
        $this->assertInstanceOf(MevonPayVtuProvider::class, $resolver->active());
    }
}
