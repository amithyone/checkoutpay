<?php

namespace Tests\Feature\Quarantine;

use App\Services\Quarantine\QuarantineService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class QuarantineModeTest extends TestCase
{
    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockPath = storage_path('framework/quarantine.lock');
        if (is_file($this->lockPath)) {
            @unlink($this->lockPath);
        }
        config([
            'checkout.quarantine.enabled' => false,
            'checkout.quarantine.unlock_code' => 'test-unlock-code-at-least-16-chars',
            'checkout.quarantine.allowed_db_hosts' => ['127.0.0.1', 'localhost', ''],
            'checkout.quarantine.allowed_db_database' => 'checkoutpay',
            'checkout.quarantine.min_payments' => 0,
            'checkout.quarantine.min_businesses' => 0,
            'checkout.quarantine.min_admins' => 0,
            'checkout.quarantine.required_tables' => [],
            'checkout.quarantine.check_interval_seconds' => 5,
            // Reset any host mutation from prior tests
            'database.connections.sqlite.host' => null,
            'database.connections.mysql.host' => '127.0.0.1',
        ]);
        \Illuminate\Support\Facades\DB::purge();
    }

    protected function tearDown(): void
    {
        if (is_file($this->lockPath)) {
            @unlink($this->lockPath);
        }
        parent::tearDown();
    }

    public function test_status_ok_when_disabled(): void
    {
        $response = $this->getJson('/quarantine/status');
        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('enabled', false);
    }

    public function test_wrong_db_host_trips_quarantine_and_blocks_home(): void
    {
        config([
            'checkout.quarantine.enabled' => true,
            'checkout.quarantine.allowed_db_hosts' => ['only-allowed-host.example'],
            'database.connections.'.config('database.default').'.host' => 'evil.attacker.example',
        ]);

        /** @var QuarantineService $q */
        $q = app(QuarantineService::class);
        $this->assertTrue($q->guard());
        $this->assertTrue($q->isLocked());

        $this->get('/')->assertStatus(503);
        $this->getJson('/api/health')->assertStatus(503)
            ->assertJsonPath('status', 'quarantine');

        $this->getJson('/quarantine/status')
            ->assertStatus(503)
            ->assertJsonPath('active', true)
            ->assertJsonFragment(['db_host_not_allowed']);
    }

    public function test_unlock_with_valid_code_clears_lock_when_fingerprint_ok(): void
    {
        File::put($this->lockPath, json_encode([
            'tripped_at' => now()->toIso8601String(),
            'reasons' => ['already_locked'],
        ]));

        // Disable fingerprint re-arm for this HTTP unlock path (re-arm covered by service/guard tests).
        config([
            'checkout.quarantine.enabled' => false,
            'checkout.quarantine.unlock_code' => 'test-unlock-code-at-least-16-chars',
        ]);

        $response = $this->postJson('/quarantine/unlock', [
            'code' => 'test-unlock-code-at-least-16-chars',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertFalse(is_file($this->lockPath));
    }

    public function test_unlock_rejects_bad_code(): void
    {
        File::put($this->lockPath, json_encode(['reasons' => ['already_locked']]));

        $this->postJson('/quarantine/unlock', [
            'code' => 'wrong-code-that-is-long-enough',
        ])->assertStatus(403);

        $this->assertTrue(is_file($this->lockPath));
    }

    public function test_artisan_migrate_blocked_when_locked(): void
    {
        File::put($this->lockPath, json_encode(['reasons' => ['already_locked']]));
        config(['checkout.quarantine.enabled' => true]);

        $q = app(QuarantineService::class);
        $this->assertTrue($q->shouldBlockArtisan('migrate'));
        $this->assertTrue($q->shouldBlockArtisan('db:wipe'));
        $this->assertFalse($q->shouldBlockArtisan('quarantine:status'));
    }
}
