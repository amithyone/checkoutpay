<?php

namespace Tests\Unit\Credit;

use App\Services\Credit\OverdraftEligibilityService;
use Tests\TestCase;

class OverdraftEligibilityServiceTest extends TestCase
{
    public function test_resolve_tier_at_thresholds(): void
    {
        $svc = app(OverdraftEligibilityService::class);

        $this->assertNull($svc->resolveTier(4_999_999));
        $this->assertSame(OverdraftEligibilityService::TIER_1, $svc->resolveTier(5_000_000));
        $this->assertSame(OverdraftEligibilityService::TIER_2, $svc->resolveTier(10_000_000));
    }
}
