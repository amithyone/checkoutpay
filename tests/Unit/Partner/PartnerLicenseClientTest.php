<?php

namespace Tests\Unit\Partner;

use App\Services\Partner\PartnerLicenseClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PartnerLicenseClientTest extends TestCase
{
    public function test_not_enforced_always_ok(): void
    {
        config(['partner_license.enforced' => false]);
        $client = app(PartnerLicenseClient::class);

        $this->assertFalse($client->shouldBlockLicensedFeatures());
    }

    public function test_grace_period_after_network_failure(): void
    {
        config([
            'partner_license.enforced' => true,
            'partner_license.license_key' => 'pl_gracetest',
            'partner_license.issuer_url' => 'https://issuer.test',
            'partner_license.grace_hours' => 72,
        ]);

        $cacheKey = 'partner_license:client:'.sha1('pl_gracetest');
        Cache::put($cacheKey.':last_success', now()->subHours(1)->toIso8601String(), now()->addDay());

        Http::fake([
            'issuer.test/*' => Http::response([], 500),
        ]);

        $client = app(PartnerLicenseClient::class);
        $ping = $client->ping(true);

        $this->assertTrue($ping['ok']);
        $this->assertSame('grace', $ping['status']);
        $this->assertFalse($client->shouldBlockLicensedFeatures());
    }

    public function test_verify_build_with_empty_checksums(): void
    {
        $manifestPath = base_path('partner-build-manifest.json');
        $this->assertFileExists($manifestPath);

        $client = app(PartnerLicenseClient::class);
        $result = $client->verifyBuildManifest();

        $this->assertTrue($result['ok']);
    }
}
