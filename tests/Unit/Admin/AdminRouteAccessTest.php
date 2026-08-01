<?php

namespace Tests\Unit\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_support_can_open_dashboard_when_page_permissions_omit_dashboard(): void
    {
        $admin = Admin::create([
            'name' => 'Wallet Agent',
            'email' => 'wallet-agent@example.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_WALLET_SUPPORT,
            'is_active' => true,
            'admin_page_permissions' => ['support', 'whatsapp_wallet_users'],
        ]);

        $this->assertFalse($admin->canAccessPage('dashboard'));
        $this->assertTrue($admin->canAccessRoute('admin.dashboard'));
        $this->assertTrue($admin->canAccessRoute('admin.profile.index'));
    }
}
