<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class CronTokenMiddlewareTest extends TestCase
{
    public function test_web_cron_without_token_returns_401_when_configured(): void
    {
        config(['checkout.cron_api_token' => 'secret-cron-token']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('/cron/fill-sender-names')->assertUnauthorized();
        $this->get('/cron/process-emails')->assertUnauthorized();
    }

    public function test_web_cron_with_wrong_token_returns_401(): void
    {
        config(['checkout.cron_api_token' => 'secret-cron-token']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('/cron/fill-sender-names?token=wrong')->assertUnauthorized();
    }

    public function test_production_without_token_configured_returns_503(): void
    {
        config(['checkout.cron_api_token' => '']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('/cron/fill-sender-names')->assertStatus(503);
    }
}
