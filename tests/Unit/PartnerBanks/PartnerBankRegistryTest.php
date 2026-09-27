<?php

namespace Tests\Unit\PartnerBanks;

use App\Services\PartnerBanks\PartnerBankRegistry;
use Tests\TestCase;

class PartnerBankRegistryTest extends TestCase
{
    public function test_resolve_active_slug_falls_back_when_default_disabled(): void
    {
        config([
            'partner_banks.default' => 'missing',
            'partner_banks.enabled' => ['mevonpay', 'acme'],
        ]);

        $registry = app(PartnerBankRegistry::class);
        $this->assertSame('mevonpay', $registry->resolveActiveSlug());
    }
}
