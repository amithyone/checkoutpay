<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\RentalsAdminAppSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RentalsAdminAppSessionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Rentals Admin',
            'email' => 'rentals-admin@example.com',
            'password' => Hash::make('secret123'),
            'role' => Admin::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_login_creates_app_session_and_logout_ends_it(): void
    {
        $this->makeAdmin();

        $login = $this->postJson('/api/v1/rentals/admin/login', [
            'email' => 'rentals-admin@example.com',
            'password' => 'secret123',
            'client_context' => [
                'platform' => 'ios',
                'app_version' => '1.2.0',
                'device_label' => 'iPhone QA',
            ],
        ]);

        $login->assertOk()
            ->assertJsonStructure(['token', 'app_session_id', 'admin' => ['id', 'email']]);

        $sessionUuid = (string) $login->json('app_session_id');
        $this->assertNotEmpty($sessionUuid);

        $session = RentalsAdminAppSession::query()->where('session_uuid', $sessionUuid)->first();
        $this->assertNotNull($session);
        $this->assertSame('password', $session->login_method);
        $this->assertSame('ios', $session->platform);
        $this->assertSame('iPhone QA', $session->device_label);
        $this->assertNull($session->ended_at);

        $token = (string) $login->json('token');

        $this->postJson('/api/v1/rentals/admin/logout', [], [
            'Authorization' => 'Bearer '.$token,
            'X-App-Session-Id' => $sessionUuid,
        ])->assertOk();

        $session->refresh();
        $this->assertNotNull($session->ended_at);
    }

    public function test_idle_app_session_returns_401_and_ends_session(): void
    {
        config(['rentals_admin.app_session_idle_minutes' => 10]);

        $this->makeAdmin();

        $login = $this->postJson('/api/v1/rentals/admin/login', [
            'email' => 'rentals-admin@example.com',
            'password' => 'secret123',
        ]);

        $login->assertOk();
        $token = (string) $login->json('token');
        $sessionUuid = (string) $login->json('app_session_id');

        $session = RentalsAdminAppSession::query()->where('session_uuid', $sessionUuid)->first();
        $this->assertNotNull($session);
        $session->forceFill(['last_seen_at' => now()->subMinutes(11)])->save();

        $admin = $session->admin;
        $this->assertNotNull($admin);
        $tokenModel = $admin->tokens()->latest('id')->first();
        $this->assertNotNull($tokenModel);
        $tokenModel->forceFill(['expires_at' => now()->addHour()])->save();

        $this->getJson('/api/v1/rentals/admin/me', [
            'Authorization' => 'Bearer '.$token,
            'X-App-Session-Id' => $sessionUuid,
        ])->assertStatus(401)
            ->assertJsonPath('code', 'session_expired');

        $session->refresh();
        $this->assertNotNull($session->ended_at);
    }
}
