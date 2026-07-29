<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_payment_gets_default_expiry(): void
    {
        Setting::set('transaction_pending_time_minutes', 60, 'integer', 'payment');

        $expiresAt = Payment::defaultExpiresAtForService('general', false);

        $this->assertNotNull($expiresAt);
        $this->assertTrue($expiresAt->greaterThan(now()->addMinutes(59)));
        $this->assertTrue($expiresAt->lessThanOrEqualTo(now()->addMinutes(61)));
    }

    public function test_invoice_and_membership_do_not_expire(): void
    {
        $this->assertNull(Payment::defaultExpiresAtForService('invoice', true));
        $this->assertNull(Payment::defaultExpiresAtForService('membership', false));
    }

    private function makeBusiness(): Business
    {
        return Business::create([
            'name' => 'Expiry Test Biz',
            'email' => 'expiry-biz-'.uniqid().'@test.com',
            'api_key' => 'pk_exp_'.uniqid(),
            'is_active' => true,
            'balance' => 0,
        ]);
    }

    public function test_should_stay_pending_indefinitely_only_for_invoice_and_membership(): void
    {
        $business = $this->makeBusiness();

        $regular = Payment::create([
            'transaction_id' => 'TXN-REG-1',
            'amount' => 1000,
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'webhook_url' => '',
            'email_data' => ['service' => 'general'],
        ]);

        $membership = Payment::create([
            'transaction_id' => 'TXN-MEM-1',
            'amount' => 2000,
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'webhook_url' => '',
            'email_data' => ['service' => 'membership', 'membership_id' => 1],
        ]);

        $invoice = Payment::create([
            'transaction_id' => 'TXN-INV-1',
            'amount' => 3000,
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'webhook_url' => '',
            'email_data' => ['service' => 'invoice'],
        ]);

        $this->assertFalse($regular->shouldStayPendingIndefinitely());
        $this->assertTrue($membership->shouldStayPendingIndefinitely());
        $this->assertTrue($invoice->shouldStayPendingIndefinitely());
    }

    public function test_payment_expire_command_rejects_stale_regular_pending_rows(): void
    {
        Setting::set('transaction_pending_time_minutes', 60, 'integer', 'payment');

        $business = $this->makeBusiness();

        $stale = Payment::create([
            'transaction_id' => 'TXN-STALE-1',
            'amount' => 5000,
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'webhook_url' => '',
            'email_data' => ['service' => 'general'],
        ]);
        $stale->forceFill([
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ])->saveQuietly();

        $membership = Payment::create([
            'transaction_id' => 'TXN-MEM-STALE',
            'amount' => 5000,
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'webhook_url' => '',
            'email_data' => ['service' => 'membership', 'membership_id' => 9],
        ]);
        $membership->forceFill([
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ])->saveQuietly();

        $this->artisan('payment:expire')->assertSuccessful();

        $stale->refresh();
        $membership->refresh();

        $this->assertSame(Payment::STATUS_REJECTED, $stale->status);
        $this->assertSame(Payment::STATUS_PENDING, $membership->status);
    }
}
