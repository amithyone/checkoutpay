<?php

namespace Tests\Unit;

use App\Models\Business;
use PHPUnit\Framework\TestCase;

class BusinessCacNormalizationTest extends TestCase
{
    public function test_normalizes_rc_and_bn_numbers(): void
    {
        $this->assertSame('RC1234567', Business::normalizeCacRegistrationNumber('rc 1234567'));
        $this->assertSame('BN9876543', Business::normalizeCacRegistrationNumber('BN-9876543'));
        $this->assertSame('RC100', Business::normalizeCacRegistrationNumber(' RC100 '));
    }
}
