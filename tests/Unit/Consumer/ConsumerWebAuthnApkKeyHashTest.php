<?php

namespace Tests\Unit\Consumer;

use App\Services\Consumer\ConsumerWebAuthnService;
use PHPUnit\Framework\TestCase;

class ConsumerWebAuthnApkKeyHashTest extends TestCase
{
    public function test_converts_colon_fingerprint_to_apk_key_hash(): void
    {
        $fp = '1C:E8:36:89:E0:FF:AF:DC:37:7A:CB:55:F5:02:BF:22:F7:00:25:15:90:E8:7F:55:DD:FA:21:41:B0:9B:FC:AD';
        $hash = ConsumerWebAuthnService::sha256FingerprintToApkKeyHash($fp);

        $this->assertNotNull($hash);
        $this->assertSame(43, strlen($hash)); // 32 bytes → base64url without padding
        $this->assertStringNotContainsString('+', $hash);
        $this->assertStringNotContainsString('/', $hash);
        $this->assertStringNotContainsString('=', $hash);
    }

    public function test_rejects_invalid_fingerprint(): void
    {
        $this->assertNull(ConsumerWebAuthnService::sha256FingerprintToApkKeyHash(''));
        $this->assertNull(ConsumerWebAuthnService::sha256FingerprintToApkKeyHash('AA:BB'));
    }
}
