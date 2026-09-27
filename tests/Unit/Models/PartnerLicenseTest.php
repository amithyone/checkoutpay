<?php

namespace Tests\Unit\Models;

use App\Models\PartnerLicense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerLicenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_allowed_when_list_empty(): void
    {
        $license = new PartnerLicense([
            'allowed_hosts' => [],
        ]);

        $this->assertTrue($license->hostAllowed('pay.example.com'));
    }

    public function test_host_wildcard_and_exact_match(): void
    {
        $license = new PartnerLicense([
            'allowed_hosts' => ['pay.example.com', '*.example.com'],
        ]);

        $this->assertTrue($license->hostAllowed('pay.example.com'));
        $this->assertTrue($license->hostAllowed('api.example.com'));
        $this->assertFalse($license->hostAllowed('evil.com'));
    }

    public function test_build_version_compare(): void
    {
        $license = new PartnerLicense([
            'min_build_version' => '1.2.0',
        ]);

        $this->assertTrue($license->buildAllowed('1.2.0'));
        $this->assertTrue($license->buildAllowed('1.3.0'));
        $this->assertFalse($license->buildAllowed('1.1.9'));
        $this->assertFalse($license->buildAllowed(''));
    }
}
