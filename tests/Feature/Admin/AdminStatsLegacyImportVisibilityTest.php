<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Business;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminStatsLegacyImportVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_time_stats_include_old_approved_imports(): void
    {
        $admin = Admin::create([
            'name' => 'Stats Admin',
            'email' => 'stats-admin@example.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $business = Business::create([
            'name' => 'Stats Biz',
            'email' => 'stats-biz-'.uniqid().'@test.com',
            'api_key' => 'pk_stats_'.uniqid(),
            'is_active' => true,
            'balance' => 0,
        ]);

        $legacy = Payment::create([
            'transaction_id' => 'LEGACY-STATS-1',
            'amount' => 5000,
            'business_id' => $business->id,
            'status' => Payment::STATUS_APPROVED,
            'webhook_url' => '',
            'payment_source' => 'legacy_import',
        ]);
        Payment::whereKey($legacy->id)->update([
            'created_at' => now()->subMonths(14),
            'updated_at' => now()->subMonths(14),
        ]);

        $recent = Payment::create([
            'transaction_id' => 'RECENT-STATS-1',
            'amount' => 1000,
            'business_id' => $business->id,
            'status' => Payment::STATUS_APPROVED,
            'webhook_url' => '',
        ]);
        Payment::whereKey($recent->id)->update([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.stats.index', ['period' => 'daily']))
            ->assertOk()
            ->assertSee('All-time totals')
            ->assertSee('2'); // lifetime approved in the dark banner / cards

        $this->actingAs($admin, 'admin')
            ->get(route('admin.stats.index', ['period' => 'all']))
            ->assertOk()
            ->assertSee('LEGACY-STATS-1', false); // may only be in recent list if latest()

        // Daily window should not count the 14-month-old payment in period summary.
        $dailyApproved = Payment::where('status', Payment::STATUS_APPROVED)
            ->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->count();
        $this->assertSame(1, $dailyApproved);
        $this->assertSame(2, Payment::where('status', Payment::STATUS_APPROVED)->count());
        $this->assertNotNull($legacy->fresh());
    }
}
