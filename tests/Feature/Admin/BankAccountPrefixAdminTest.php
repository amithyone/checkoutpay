<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\BankAccountPrefixRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BankAccountPrefixAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_support_can_add_prefix_rule(): void
    {
        $admin = Admin::create([
            'name' => 'Wallet Agent',
            'email' => 'prefix-agent@example.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_WALLET_SUPPORT,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post('/enter0/bank-account-prefixes', [
                'prefix' => '991',
                'bank_code' => '100004',
                'bank_name' => 'OPay',
                'notes' => 'Test series',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bank_account_prefix_rules', [
            'prefix' => '991',
            'bank_code' => '100004',
            'bank_name' => 'OPay',
            'created_by_admin_id' => $admin->id,
        ]);

        Cache::forget(BankAccountPrefixRule::cacheKey());

        $this->getJson('/api/v1/rentals/banks/suggestions?account=9911234567')
            ->assertOk()
            ->assertJsonPath('data.banks.0.code', '100004');
    }

    public function test_wallet_support_can_open_prefix_admin_page(): void
    {
        $admin = Admin::create([
            'name' => 'Wallet Agent',
            'email' => 'prefix-page@example.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_WALLET_SUPPORT,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/enter0/bank-account-prefixes')
            ->assertOk()
            ->assertSee('Bank account prefixes');
    }
}
