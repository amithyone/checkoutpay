<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionKeepAliveTest extends TestCase
{
    public function test_keepalive_returns_csrf_token(): void
    {
        $response = $this->getJson(route('session.keepalive'));

        $response->assertOk()
            ->assertJsonStructure(['ok', 'csrf_token', 'lifetime_minutes']);
        $this->assertNotEmpty($response->json('csrf_token'));
    }

    public function test_admin_session_lifetime_is_extended(): void
    {
        $prefix = trim((string) config('admin.path', 'enter0'), '/') ?: 'enter0';

        $this->get('/'.$prefix.'/login');

        $this->assertGreaterThanOrEqual(
            (int) config('session.admin_investor_lifetime'),
            (int) config('session.lifetime')
        );
    }
}
