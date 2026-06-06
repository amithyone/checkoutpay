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
}
