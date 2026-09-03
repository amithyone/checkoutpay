<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendWebhookNotification;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SendWebhookNotificationBroadcastSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_payload_loads_broadcast_session_without_id_column(): void
    {
        if (! Schema::hasTable('broadcast_sessions') || ! Schema::hasColumn('broadcast_sessions', 'payment_id')) {
            $this->markTestSkipped('broadcast_sessions.payment_id is not in the test schema.');
        }

        $this->assertFalse(Schema::hasColumn('broadcast_sessions', 'id'));

        $payment = Payment::create([
            'transaction_id' => 'TXN-WH-SESSION-1',
            'amount' => 50.00,
            'received_amount' => 50.00,
            'status' => Payment::STATUS_APPROVED,
            'webhook_url' => 'https://example.com/ipn/checkoutnow',
            'payer_name' => 'Jane Customer',
            'email_data' => [],
        ]);

        $sessionUuid = 'aa0e8400-e29b-41d4-a716-446655440099';
        $session = [
            'session_uuid' => $sessionUuid,
            'terminal_id' => 'TERM-WH',
            'status' => 'paid',
            'amount_ngn' => 5000,
            'opened_at' => (int) (microtime(true) * 1000),
            'payment_id' => $payment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('broadcast_sessions', 'amount_received_ngn')) {
            $session['amount_received_ngn'] = 5000;
        }
        Schema::disableForeignKeyConstraints();
        DB::table('broadcast_sessions')->insert($session);
        Schema::enableForeignKeyConstraints();

        $job = new SendWebhookNotification($payment);
        $method = new \ReflectionMethod($job, 'buildWebhookPayload');
        $payload = $method->invoke($job);

        $this->assertSame('payment.approved', $payload['event']);
        $this->assertSame($sessionUuid, $payload['session_id']);
        $this->assertSame('payment.confirmed', $payload['broadcast_event']);
    }
}
