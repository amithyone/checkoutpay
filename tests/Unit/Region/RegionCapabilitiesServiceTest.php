<?php

namespace Tests\Unit\Region;

use App\Services\Region\RegionCapabilitiesService;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RegionCapabilitiesServiceTest extends TestCase
{
    public function test_kenya_phone_normalizes_and_resolves_capabilities(): void
    {
        $this->assertSame('254712345678', PhoneNormalizer::canonicalKeE164Digits('0712345678'));
        $this->assertSame('254712345678', PhoneNormalizer::canonicalAuthE164Digits('+254712345678'));
        $this->assertSame('KE', PhoneNormalizer::countryIsoFromE164('254712345678'));

        Cache::forever('cashwyre_kenya_capabilities', [
            'bank_payout' => false,
            'mpesa_payout' => true,
            'mpesa_collection' => true,
            'bills' => false,
            'airtime' => false,
        ]);

        $region = app(RegionCapabilitiesService::class)->forPhone('+254712345678');

        $this->assertSame('KE', $region['country']);
        $this->assertSame('KES', $region['currency']);
        $this->assertSame('kenya_region', $region['platform']);
        $this->assertSame('cashwyre', $region['rails']['primary']);
        $this->assertSame('mga_planned', $region['rails']['dedicated_partner']);
        $this->assertTrue($region['features']['p2p']);
        $this->assertTrue($region['features']['cross_border_p2p']);
        $this->assertFalse($region['features']['bank_payin_va']);
        $this->assertFalse($region['features']['bank_payout']);
        $this->assertTrue($region['features']['mpesa_payout']);
        $this->assertFalse($region['features']['vtu_ng']);
    }

    public function test_nigeria_capabilities_keep_mevon_rails(): void
    {
        $region = app(RegionCapabilitiesService::class)->forPhone('+2348012345678');

        $this->assertSame('NG', $region['country']);
        $this->assertSame('mevonpay', $region['rails']['primary']);
        $this->assertTrue($region['features']['bank_payin_va']);
        $this->assertTrue($region['features']['vtu_ng']);
        $this->assertFalse($region['features']['mpesa_payout']);
    }
}
