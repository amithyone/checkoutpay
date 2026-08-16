<?php

namespace Tests\Unit\Support;

use App\Models\Business;
use App\Support\ApiHitWebsiteResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiHitWebsiteResolverTest extends TestCase
{
    public function test_uses_origin_and_referer_headers(): void
    {
        $request = Request::create('https://check-outpay.com/api/v1/payment-request', 'POST');
        $request->headers->set('Origin', 'https://shop.example.com');
        $request->headers->set('Referer', 'https://shop.example.com/checkout');

        $ctx = ApiHitWebsiteResolver::fromRequest($request, null);

        $this->assertSame('https://shop.example.com', $ctx['origin']);
        $this->assertSame('https://shop.example.com/checkout', $ctx['referer']);
        $this->assertSame('shop.example.com', $ctx['website_host']);
    }

    public function test_uses_website_url_from_body_when_headers_missing(): void
    {
        $request = Request::create(
            'https://check-outpay.com/api/v1/payment-request',
            'POST',
            ['website_url' => 'https://pay.merchant.ng', 'webhook_url' => 'https://pay.merchant.ng/hooks']
        );

        $ctx = ApiHitWebsiteResolver::fromRequest($request, null);

        $this->assertSame('https://pay.merchant.ng', $ctx['origin']);
        $this->assertSame('pay.merchant.ng', $ctx['website_host']);
        $this->assertNotNull($ctx['referer']);
    }

    public function test_falls_back_to_business_website_column(): void
    {
        $request = Request::create('https://check-outpay.com/api/v1/withdrawal', 'POST');
        $business = new Business(['website' => 'https://store.example.org']);

        $ctx = ApiHitWebsiteResolver::fromRequest($request, $business);

        $this->assertSame('store.example.org', $ctx['website_host']);
    }
}
