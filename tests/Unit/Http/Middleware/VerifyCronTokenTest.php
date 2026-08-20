<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\VerifyCronToken;
use Illuminate\Http\Request;
use Tests\TestCase;

class VerifyCronTokenTest extends TestCase
{
    public function test_rejects_when_token_configured_but_missing(): void
    {
        config(['checkout.cron_api_token' => 'secret-cron-token']);

        $middleware = new VerifyCronToken;
        $request = Request::create('/api/v1/transaction/check', 'GET');
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_allows_matching_header_token(): void
    {
        config(['checkout.cron_api_token' => 'secret-cron-token']);

        $middleware = new VerifyCronToken;
        $request = Request::create('/api/v1/cron/process-webhooks', 'GET');
        $request->headers->set('X-Cron-Token', 'secret-cron-token');
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_allows_matching_query_token(): void
    {
        config(['checkout.cron_api_token' => 'secret-cron-token']);

        $middleware = new VerifyCronToken;
        $request = Request::create('/api/v1/statistics?token=secret-cron-token', 'GET');
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_production_without_token_configured_returns_503(): void
    {
        config(['checkout.cron_api_token' => '']);
        $this->app->detectEnvironment(fn () => 'production');

        $middleware = new VerifyCronToken;
        $request = Request::create('/api/v1/transaction/check', 'GET');
        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertSame(503, $response->getStatusCode());
    }
}
