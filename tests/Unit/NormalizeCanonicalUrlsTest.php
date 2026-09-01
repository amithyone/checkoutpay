<?php

namespace Tests\Unit;

use App\Http\Middleware\NormalizeCanonicalUrls;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NormalizeCanonicalUrlsTest extends TestCase
{
    #[Test]
    public function it_redirects_trailing_slash_urls_to_canonical_path(): void
    {
        config(['app.url' => 'https://check-outpay.com']);

        $middleware = new NormalizeCanonicalUrls;
        $request = Request::create('/faqs/', 'GET');
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('https://check-outpay.com/faqs', $response->headers->get('Location'));
    }

    #[Test]
    public function it_does_not_redirect_alias_hosts_to_app_url(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.url' => 'https://check-outnow.com',
            'checkout.canonical_alias_hosts' => [
                'check-outpay.com',
                'www.check-outpay.com',
                'check-outnow.com',
                'www.check-outnow.com',
            ],
        ]);

        $middleware = new NormalizeCanonicalUrls;
        $request = Request::create('https://check-outpay.com/', 'GET');
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->headers->get('Location'));

        $investor = Request::create('https://check-outpay.com/investor/access/abc', 'GET');
        $investorResponse = $middleware->handle($investor, fn () => response('ok'));
        $this->assertSame(200, $investorResponse->getStatusCode());
    }
}
