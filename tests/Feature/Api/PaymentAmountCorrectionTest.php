<?php

namespace Tests\Feature\Api;

use App\Jobs\CheckPaymentEmails;
use App\Models\Business;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PaymentAmountCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
    }

    /** @test */
    public function it_updates_pending_payment_amount_and_dispatches_check_payment_emails(): void
    {
        $business = Business::create([
            'name' => 'Test Business',
            'email' => 'business@test.com',
            'api_key' => 'pk_test_123',
            'is_active' => true,
        ]);

        $payment = Payment::create([
            'transaction_id' => 'TXN-' . uniqid(),
            'amount' => 5000.00,
            'payer_name' => 'John Doe',
            'webhook_url' => 'https://example.com/webhook',
            'business_id' => $business->id,
            'status' => Payment::STATUS_PENDING,
            'account_number' => '0123456789',
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->patchJson("/api/v1/payment/{$payment->transaction_id}/amount", [
            'new_amount' => 7500.00,
        ], [
            'X-API-Key' => $business->api_key,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.transaction_id', $payment->transaction_id);
        $response->assertJsonPath('data.amount', 7500.0);
        $response->assertJsonPath('data.status', Payment::STATUS_PENDING);

        $payment->refresh();
        $this->assertSame(7500.00, (float) $payment->amount);

        Bus::assertDispatched(CheckPaymentEmails::class, function ($job) use ($payment) {
            return $job->payment->id === $payment->id;
        });
    }

    /** @test */
    public function it_rejects_amount_update_when_payment_not_pending(): void
    {
        $business = Business::create([
            'name' => 'Test Business',
            'email' => 'business2@test.com',
            'api_key' => 'pk_test_456',
            'is_active' => true,
        ]);

        $payment = Payment::create([
            'transaction_id' => 'TXN-' . uniqid(),
            'amount' => 5000.00,
            'payer_name' => 'Jane Doe',
            'webhook_url' => 'https://example.com/webhook',
            'business_id' => $business->id,
            'status' => 'approved',
            'account_number' => '0123456789',
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->patchJson("/api/v1/payment/{$payment->transaction_id}/amount", [
            'new_amount' => 7500.00,
        ], [
            'X-API-Key' => $business->api_key,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);

        Bus::assertNotDispatched(CheckPaymentEmails::class);
    }

    /** @test */
    public function it_returns_404_for_unknown_transaction(): void
    {
        $business = Business::create([
            'name' => 'Test Business',
            'email' => 'business3@test.com',
            'api_key' => 'pk_test_789',
            'is_active' => true,
        ]);

        $response = $this->patchJson('/api/v1/payment/TXN-NONEXISTENT/amount', [
            'new_amount' => 1000.00,
        ], [
            'X-API-Key' => $business->api_key,
        ]);

        $response->assertNotFound();
        $response->assertJsonPath('success', false);
    }
}
