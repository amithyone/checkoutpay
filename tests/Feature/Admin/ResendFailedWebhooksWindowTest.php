<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendWebhookNotification;
use App\Models\Admin;
use App\Models\Business;
use App\Models\Payment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
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

    public function test_six_hour_button_queues_only_recent_failed_webhooks(): void
    {
        $this->actingSuperAdmin();
        Queue::fake();

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
            ->assertJsonPath('queued_count', 1)
            ->assertJsonPath('hours', 6);

        Queue::assertPushed(SendWebhookNotification::class, 1);
        Queue::assertPushed(SendWebhookNotification::class, function (SendWebhookNotification $job) use ($recent) {
            return $job->payment->is($recent);
        });

        $this->assertNotNull($mid->id);
        $this->assertNotNull($old->id);
    }

    public function test_twenty_four_hour_button_excludes_older_failures(): void
    {
        $this->actingSuperAdmin();
        Queue::fake();

        $business = Business::create([
            'name' => 'Webhook Window Biz 24',
            'email' => 'wh-window24-'.uniqid().'@test.com',
            'api_key' => 'pk_wh24_'.uniqid(),
            'is_active' => true,
            'balance' => 0,
        ]);

        $this->failedPayment($business->id, 'TXN-FAIL-20H', now()->subHours(20));
        $this->failedPayment($business->id, 'TXN-FAIL-30H-B', now()->subHours(30));

        $this->postJson(route('admin.payments.resend-failed-webhooks'), ['hours' => 24])
            ->assertOk()
            ->assertJsonPath('queued_count', 1);

        Queue::assertPushed(SendWebhookNotification::class, 1);
    }

    public function test_invalid_window_is_rejected(): void
    {
        $this->actingSuperAdmin();

        $this->postJson(route('admin.payments.resend-failed-webhooks'), ['hours' => 48])
            ->assertStatus(422);
    }

    private function failedPayment(int $businessId, string $txn, $when, string $status = 'failed'): Payment
    {
        return Payment::create([
            'transaction_id' => $txn,
            'amount' => 1000,
            'business_id' => $businessId,
            'status' => Payment::STATUS_APPROVED,
            'webhook_url' => 'https://merchant.example/webhook',
            'email_data' => ['service' => 'general'],
            'webhook_status' => $status,
            'webhook_sent_at' => $when,
            'matched_at' => $when,
        ]);
    }
}
