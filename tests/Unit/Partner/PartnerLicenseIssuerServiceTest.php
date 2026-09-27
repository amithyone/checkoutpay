<?php

namespace Tests\Unit\Partner;

use App\Models\PartnerLicense;
use App\Services\Partner\PartnerLicenseIssuerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerLicenseIssuerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['partner_license.issuer_enabled' => true]);
    }

    public function test_ping_rejects_unknown_key(): void
    {
        $issuer = app(PartnerLicenseIssuerService::class);
        $result = $issuer->handlePing('pl_invalid', ['hostname' => 'localhost']);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid', $result['status']);
    }

    public function test_ping_accepts_valid_license(): void
    {
        $license = PartnerLicense::query()->create([
            'partner_name' => 'Acme',
            'license_key' => 'pl_testkey123',
            'allowed_hosts' => ['pay.acme.test'],
            'min_build_version' => '1.0.0',
            'features' => ['wallet', 'payout'],
        ]);

        $issuer = app(PartnerLicenseIssuerService::class);
        $result = $issuer->handlePing($license->license_key, [
            'hostname' => 'pay.acme.test',
            'build_version' => '1.0.0',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('active', $result['status']);
        $this->assertContains('wallet', $result['features']);
    }

    public function test_ping_rejects_host_mismatch(): void
    {
        PartnerLicense::query()->create([
            'partner_name' => 'Acme',
            'license_key' => 'pl_hosttest',
            'allowed_hosts' => ['allowed.test'],
            'min_build_version' => '1.0.0',
        ]);

        $issuer = app(PartnerLicenseIssuerService::class);
        $result = $issuer->handlePing('pl_hosttest', ['hostname' => 'other.test', 'build_version' => '1.0.0']);

        $this->assertFalse($result['ok']);
        $this->assertSame('host_mismatch', $result['status']);
    }
}
