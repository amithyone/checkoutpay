<?php

namespace Tests\Feature\Api;

use App\Events\PaymentApproved;
use App\Models\Payment;
use App\Services\Broadcast\BroadcastSessionPaymentMatcher;
use App\Services\Broadcast\BroadcastSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BroadcastSessionPaymentMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_settlement_marks_session_paid_on_matching_transfer(): void
    {
        $this->seedTerminal(businessId: 1, permanent: false);
        $sessionUuid = 'aa0e8400-e29b-41d4-a716-446655440020';

        DB::table('broadcast_sessions')->insert([
            'session_uuid' => $sessionUuid,
            'terminal_id' => 'TERM-MATCH',
            'status' => BroadcastSessionService::STATUS_OPEN,
            'settlement_mode' => 'temporary',
            'amount_ngn' => 5000,
            'amount_received_ngn' => 0,
            'settlement_account_number' => '1000004863',
            'opened_at' => (int) (microtime(true) * 1000),
            'expecting_payment_at' => (int) (microtime(true) * 1000),
            'closed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = Payment::create([
            'transaction_id' => 'TXN-MATCH-1',
            'business_id' => 1,
            'status' => Payment::STATUS_APPROVED,
            'amount' => 50.00,
            'received_amount' => 50.00,
            'account_number' => '1000004863',
            'webhook_url' => 'https://example.com/webhook',
            'payer_name' => 'Jane Customer',
            'payer_account_number' => '0123456789',
            'bank' => 'GTBank',
        ]);

        app(BroadcastSessionPaymentMatcher::class)->handleApprovedPayment($payment);

        $this->assertDatabaseHas('broadcast_sessions', [
            'session_uuid' => $sessionUuid,
            'status' => BroadcastSessionService::STATUS_PAID,
            'amount_received_ngn' => 5000,
            'payer_name' => 'Jane Customer',
            'payer_account' => '0123456789',
            'payer_bank' => 'GTBank',
            'payer_reference' => 'TXN-MATCH-1',
        ]);
    }

    public function test_permanent_settlement_requires_expecting_payment_and_exact_amount(): void
    {
        $this->seedTerminal(businessId: 2, permanent: true);
        $sessionUuid = 'bb0e8400-e29b-41d4-a716-446655440021';

        DB::table('broadcast_sessions')->insert([
            'session_uuid' => $sessionUuid,
            'terminal_id' => 'TERM-MATCH',
            'status' => BroadcastSessionService::STATUS_OPEN,
            'settlement_mode' => 'permanent',
            'amount_ngn' => 2500,
            'amount_received_ngn' => 0,
            'settlement_account_number' => '1000004863',
            'opened_at' => (int) (microtime(true) * 1000),
            'expecting_payment_at' => null,
            'closed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = Payment::create([
            'transaction_id' => 'TXN-MATCH-2',
            'business_id' => 2,
            'status' => Payment::STATUS_APPROVED,
            'amount' => 25.00,
            'received_amount' => 25.00,
            'account_number' => '1000004863',
            'webhook_url' => 'https://example.com/webhook',
        ]);

        app(BroadcastSessionPaymentMatcher::class)->handleApprovedPayment($payment);

        $this->assertDatabaseHas('broadcast_sessions', [
            'session_uuid' => $sessionUuid,
            'status' => BroadcastSessionService::STATUS_OPEN,
        ]);

        app(BroadcastSessionPaymentMatcher::class)->markExpectingPayment($sessionUuid, 'TERM-MATCH');
        app(BroadcastSessionPaymentMatcher::class)->handleApprovedPayment($payment->fresh());

        $this->assertDatabaseHas('broadcast_sessions', [
            'session_uuid' => $sessionUuid,
            'status' => BroadcastSessionService::STATUS_PAID,
            'amount_received_ngn' => 2500,
        ]);
    }

    public function test_temporary_partial_transfer_leaves_session_partial(): void
    {
        $this->seedTerminal(businessId: 3, permanent: false);
        $sessionUuid = 'cc0e8400-e29b-41d4-a716-446655440022';

        DB::table('broadcast_sessions')->insert([
            'session_uuid' => $sessionUuid,
            'terminal_id' => 'TERM-MATCH',
            'status' => BroadcastSessionService::STATUS_OPEN,
            'settlement_mode' => 'temporary',
            'amount_ngn' => 5000,
            'amount_received_ngn' => 0,
            'settlement_account_number' => '1000004863',
            'opened_at' => (int) (microtime(true) * 1000),
            'expecting_payment_at' => (int) (microtime(true) * 1000),
            'closed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = Payment::create([
            'transaction_id' => 'TXN-MATCH-3',
            'business_id' => 3,
            'status' => Payment::STATUS_APPROVED,
            'amount' => 20.00,
            'received_amount' => 20.00,
            'account_number' => '1000004863',
            'webhook_url' => 'https://example.com/webhook',
        ]);

        app(BroadcastSessionPaymentMatcher::class)->handleApprovedPayment($payment);

        $this->assertDatabaseHas('broadcast_sessions', [
            'session_uuid' => $sessionUuid,
            'status' => BroadcastSessionPaymentMatcher::STATUS_PARTIAL,
            'amount_received_ngn' => 2000,
        ]);
    }

    public function test_expect_payment_endpoint_marks_session_awaiting(): void
    {
        $this->seedTerminal(businessId: 4, permanent: true);
        $sessionUuid = 'dd0e8400-e29b-41d4-a716-446655440023';

        DB::table('broadcast_sessions')->insert([
            'session_uuid' => $sessionUuid,
            'terminal_id' => 'TERM-MATCH',
            'status' => BroadcastSessionService::STATUS_OPEN,
            'settlement_mode' => 'permanent',
            'amount_ngn' => 1500,
            'amount_received_ngn' => 0,
            'settlement_account_number' => '1000004863',
            'opened_at' => (int) (microtime(true) * 1000),
            'expecting_payment_at' => null,
            'closed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/api/v1/broadcast/sessions/{$sessionUuid}/expect-payment", [
            'terminal_id' => 'TERM-MATCH',
        ], [
            'X-Terminal-Api-Key' => 'bk_test_matcher_expect',
        ])->assertOk()->assertJson([
            'ok' => true,
            'session_status' => BroadcastSessionPaymentMatcher::STATUS_AWAITING_PAYMENT,
        ]);
    }

    private function seedTerminal(int $businessId, bool $permanent): void
    {
        DB::table('businesses')->insert([
            'id' => $businessId,
            'name' => 'Match Shop '.$businessId,
            'email' => "match{$businessId}@test.com",
            'password' => bcrypt('secret'),
            'is_active' => 1,
            'broadcast_pay_at_shop_enabled' => 1,
            'broadcast_pay_at_shop_active' => 1,
            'broadcast_pay_at_shop_permanent_settlement' => $permanent ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'TERM-MATCH',
            'merchant_id' => 'MCH-MATCH',
            'api_key' => 'bk_test_matcher_expect',
            'signing_key' => '',
            'public_key' => 'dummy',
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Match Shop',
            'bank_name' => 'RUBIES MFB',
            'bank_name_hash' => 'sha256:abc',
            'masked_account_suffix' => '***4863',
            'account_number' => '1000004863',
            'recipient_bank_code' => '090175',
            'business_id' => $businessId,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
