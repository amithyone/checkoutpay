<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Business;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPaymentsPendingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_filter_includes_payments_past_expires_at(): void
    {
        $admin = Admin::create([
            'name' => 'Payments Admin',
            'email' => 'payments-admin@example.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $business = Business::create([
            'name' => 'Pending Visibility Biz',
            'email' => 'pending-vis-'.uniqid().'@test.com',
            'api_key' => 'pk_pv_'.uniqid(),
            'is_active' => true,
            'balance' => 0,
        ]);

        $stalePending = Payment::create([
            'transaction_id' => 'TXN-STALE-VISIBLE',
            'amount' => 1500,
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'webhook_url' => '',
            'email_data' => ['service' => 'general'],
            'expires_at' => now()->subMinutes(30),
        ]);

        $activePending = Payment::create([
            'transaction_id' => 'TXN-ACTIVE-VISIBLE',
            'amount' => 2500,
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'webhook_url' => '',
            'email_data' => ['service' => 'general'],
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.payments.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('TXN-STALE-VISIBLE')
            ->assertSee('TXN-ACTIVE-VISIBLE');

        $this->assertSame(Payment::STATUS_PENDING, $stalePending->fresh()->status);
        $this->assertSame(Payment::STATUS_PENDING, $activePending->fresh()->status);
    }
}
