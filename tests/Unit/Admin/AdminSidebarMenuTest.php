<?php

namespace Tests\Unit\Admin;

use App\Models\Admin;
use App\Services\Admin\AdminSidebarMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSidebarMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_support_with_explicit_page_permissions_sees_checked_pages(): void
    {
        $admin = Admin::create([
            'name' => 'Wallet Agent',
            'email' => 'wallet-sidebar@example.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_WALLET_SUPPORT,
            'is_active' => true,
            'admin_page_permissions' => ['support', 'businesses', 'business_payroll'],
        ]);

        $keys = array_column(app(AdminSidebarMenu::class)->itemsFor($admin), 'key');

        $this->assertContains('support', $keys);
        $this->assertContains('businesses', $keys);
        $this->assertContains('business_payroll', $keys);
        $this->assertNotContains('payments', $keys);
    }

    public function test_wallet_support_with_role_defaults_sees_default_pages(): void
    {
        $admin = Admin::create([
            'name' => 'Wallet Default',
            'email' => 'wallet-default@example.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_WALLET_SUPPORT,
            'is_active' => true,
        ]);

        $keys = array_column(app(AdminSidebarMenu::class)->itemsFor($admin), 'key');

        $this->assertContains('support', $keys);
        $this->assertContains('businesses', $keys);
        $this->assertContains('whatsapp_wallet_users', $keys);
        $this->assertContains('bank_account_prefixes', $keys);
        $this->assertNotContains('payments', $keys);
    }
}
