<?php

namespace Tests\Unit\Support;

use App\Support\SafeOutboundUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SafeOutboundUrlTest extends TestCase
{
    #[DataProvider('unsafeUrls')]
    public function test_rejects_private_and_metadata_targets(string $url): void
    {
        $this->assertNotNull(SafeOutboundUrl::rejectionReason($url));
        $this->assertFalse(SafeOutboundUrl::isSafe($url));
    }

    public static function unsafeUrls(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/hook'],
            'localhost' => ['http://localhost/hook'],
            'private_10' => ['http://10.0.0.5/hook'],
            'private_192' => ['http://192.168.1.1/hook'],
            'link_local_meta' => ['http://169.254.169.254/latest/meta-data'],
            'file_scheme' => ['file:///etc/passwd'],
        ];
    }

    public function test_allows_public_https_url(): void
    {
        $url = 'https://example.com/webhooks/checkout';
        $this->assertNull(SafeOutboundUrl::rejectionReason($url));
        $this->assertTrue(SafeOutboundUrl::isSafe($url));
    }
}
