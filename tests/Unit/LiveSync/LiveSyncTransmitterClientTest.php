<?php

namespace Tests\Unit\LiveSync;

use App\Services\LiveSync\LiveSyncTransmitterClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveSyncTransmitterClientTest extends TestCase
{
    public function test_sends_signed_post_to_receiver(): void
    {
        config([
            'services.live_sync.transmit_enabled' => true,
            'services.live_sync.receiver_url' => 'https://check-outnow.test/api/v1/sync/live',
            'services.live_sync.receiver_path' => '/api/v1/sync/live',
            'services.live_sync.key_id' => 'live-site-1',
            'services.live_sync.secret' => 'test-secret-value',
            'services.live_sync.source_name' => 'namecheap-live',
            'services.live_sync.timeout_seconds' => 5,
        ]);

        Http::fake([
            'https://check-outnow.test/api/v1/sync/live' => Http::response([
                'success' => true,
                'message' => 'Sync processed',
            ], 200),
        ]);

        $result = app(LiveSyncTransmitterClient::class)->send([
            'entity' => 'payment',
            'operation' => 'upsert',
            'data' => [
                'transaction_id' => 'TX-TEST-1',
                'amount' => 1000,
                'status' => 'pending',
            ],
        ]);

        $this->assertTrue($result['ok']);
        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://check-outnow.test/api/v1/sync/live') {
                return false;
            }
            $ts = $request->header('X-LiveSync-Timestamp')[0] ?? '';
            $nonce = $request->header('X-LiveSync-Nonce')[0] ?? '';
            $sig = $request->header('X-LiveSync-Signature')[0] ?? '';
            $key = $request->header('X-LiveSync-Key')[0] ?? '';
            if ($key !== 'live-site-1' || $ts === '' || $nonce === '' || $sig === '') {
                return false;
            }
            $body = $request->body();
            $canonical = implode("\n", [
                'POST',
                '/api/v1/sync/live',
                $ts,
                $nonce,
                hash('sha256', $body),
            ]);
            $expected = hash_hmac('sha256', $canonical, 'test-secret-value');

            return hash_equals($expected, $sig);
        });
    }

    public function test_disabled_transmitter_does_not_send(): void
    {
        config(['services.live_sync.transmit_enabled' => false]);
        Http::fake();

        $result = app(LiveSyncTransmitterClient::class)->send([
            'entity' => 'payment',
            'operation' => 'upsert',
            'data' => ['transaction_id' => 'TX-1', 'amount' => 1, 'status' => 'pending'],
        ]);

        $this->assertFalse($result['ok']);
        Http::assertNothingSent();
    }
}
