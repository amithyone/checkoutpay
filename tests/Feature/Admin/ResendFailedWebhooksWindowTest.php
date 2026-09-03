<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Business;
use App\Models\Payment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResendFailedWebhooksWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('payments', 'webhook_status')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('webhook_status', 32)->nullable()->default('pending');
                $table->timestamp('webhook_sent_at')->nullable();
                $table->unsignedInteger('webhook_attempts')->nullable();
                $table->text('webhook_last_error')->nullable();
            });
        }
        if (! Schema::hasColumn('payments', 'webhook_urls_sent')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->text('webhook_urls_sent')->nullable();
            });
        }
    }

    private function actingSuperAdmin(): Admin
    {
        $admin = Admin::create([
            'name' => 'Webhook Admin',
            'email' => 'webhook-admin@example.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_six_hour_button_sends_only_recent_failed_webhooks(): void
    {
        $this->actingSuperAdmin();
        config(['checkout.webhook_egress.relay_client_enabled' => false]);
        Http::fake([
            'https://example.com/*' => Http::response('OK', 200),
        ]);

        $business = Business::create([
            'name' => 'Webhook Window Biz',
            'email' => 'wh-window-'.uniqid().'@test.com',
            'api_key' => 'pk_wh_'.uniqid(),
            'is_active' => true,
            'balance' => 0,
        ]);

        $recent = $this->failedPayment($business->id, 'TXN-FAIL-2H', now()->subHours(2));
        $mid = $this->failedPayment($business->id, 'TXN-FAIL-8H', now()->subHours(8));
        $old = $this->failedPayment($business->id, 'TXN-FAIL-30H', now()->subHours(30));
        $this->failedPayment($business->id, 'TXN-SENT-2H', now()->subHours(2), 'sent');

        $this->postJson(route('admin.payments.resend-failed-webhooks'), ['hours' => 6])
            ->assertOk()
            ->assertJsonPath('sent_count', 1)
            ->assertJsonPath('delivered_count', 1)
            ->assertJsonPath('failed_count', 0)
            ->assertJsonPath('hours', 6);

        $this->assertSame('sent', $recent->fresh()->webhook_status);
        $this->assertSame('failed', $mid->fresh()->webhook_status);
        $this->assertSame('failed', $old->fresh()->webhook_status);
    }

    public function test_twenty_four_hour_button_excludes_older_failures(): void
    {
        $this->actingSuperAdmin();
        config(['checkout.webhook_egress.relay_client_enabled' => false]);
        Http::fake([
            'https://example.com/*' => Http::response('OK', 200),
        ]);

        $business = Business::create([
            'name' => 'Webhook Window Biz 24',
            'email' => 'wh-window24-'.uniqid().'@test.com',
            'api_key' => 'pk_wh24_'.uniqid(),
            'is_active' => true,
            'balance' => 0,
        ]);

        $inWindow = $this->failedPayment($business->id, 'TXN-FAIL-20H', now()->subHours(20));
        $tooOld = $this->failedPayment($business->id, 'TXN-FAIL-30H-B', now()->subHours(30));

        $this->postJson(route('admin.payments.resend-failed-webhooks'), ['hours' => 24])
            ->assertOk()
            ->assertJsonPath('sent_count', 1)
            ->assertJsonPath('delivered_count', 1);

        $this->assertSame('sent', $inWindow->fresh()->webhook_status);
        $this->assertSame('failed', $tooOld->fresh()->webhook_status);
    }

    public function test_invalid_window_is_rejected(): void
    {
        $this->actingSuperAdmin();

        $this->postJson(route('admin.payments.resend-failed-webhooks'), ['hours' => 48])
            ->assertStatus(422);
    }

    public function test_sync_resend_returns_merchant_http_response(): void
    {
        $this->actingSuperAdmin();
        config(['checkout.webhook_egress.relay_client_enabled' => false]);

        Http::fake([
            'https://example.com/*' => Http::response('IPN parse error on line 12', 500),
        ]);

        $business = Business::create([
            'name' => 'Sync Webhook Biz',
            'email' => 'wh-sync-'.uniqid().'@test.com',
            'api_key' => 'pk_whs_'.uniqid(),
            'is_active' => true,
            'balance' => 0,
        ]);

        $payment = Payment::create([
            'transaction_id' => 'TXN-SYNC-WH',
            'amount' => 1000,
            'business_id' => $business->id,
            'status' => Payment::STATUS_APPROVED,
            'webhook_url' => 'https://example.com/ipn/checkoutnow',
            'email_data' => [],
            'webhook_status' => 'failed',
        ]);

        $this->postJson(route('admin.payments.resend-webhook', $payment), ['sync' => true])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('delivered', false)
            ->assertJsonPath('attempts.0.http_status', 500)
            ->assertJsonPath('attempts.0.response_body', 'IPN parse error on line 12');
    }

    private function failedPayment(int $businessId, string $txn, $when, string $status = 'failed'): Payment
    {
        return Payment::create([
            'transaction_id' => $txn,
            'amount' => 1000,
            'business_id' => $businessId,
            'status' => Payment::STATUS_APPROVED,
            'webhook_url' => 'https://example.com/ipn/checkoutnow',
            'email_data' => ['service' => 'general'],
            'webhook_status' => $status,
            'webhook_sent_at' => $when,
            'matched_at' => $when,
        ]);
    }
}
