<?php

namespace Tests\Unit\Support;

use App\Support\WebsiteUrl;
use PHPUnit\Framework\TestCase;

class WebsiteUrlTest extends TestCase
{
    public function test_host_from_full_url(): void
    {
        $this->assertSame('shop.example.com', WebsiteUrl::hostFrom('https://www.shop.example.com/checkout'));
    }

    public function test_host_from_bare_domain(): void
    {
        $this->assertSame('shop.example.com', WebsiteUrl::hostFrom('shop.example.com'));
        $this->assertSame('shop.example.com', WebsiteUrl::hostFrom('shop.example.com/path'));
    }

    public function test_href_from_bare_domain(): void
    {
        $this->assertSame('https://shop.example.com', WebsiteUrl::hrefFrom('shop.example.com'));
    }

    public function test_hosts_match_subdomains(): void
    {
        $this->assertTrue(WebsiteUrl::hostsMatch('https://api.shop.example.com', 'shop.example.com'));
    }
}
