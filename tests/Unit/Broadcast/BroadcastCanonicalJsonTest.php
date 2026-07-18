<?php

namespace Tests\Unit\Broadcast;

use App\Http\Controllers\Api\BroadcastVerifyController;
use ReflectionClass;
use Tests\TestCase;

class BroadcastCanonicalJsonTest extends TestCase
{
    public function test_hmac_matches_python_style_compact_sorted_json(): void
    {
        $controller = new BroadcastVerifyController;
        $ref = new ReflectionClass($controller);

        $sort = $ref->getMethod('sortKeysRecursive');
        $sort->setAccessible(true);
        $canonical = $ref->getMethod('canonicalJson');
        $canonical->setAccessible(true);
        $verify = $ref->getMethod('verifySignature');
        $verify->setAccessible(true);

        $payload = [
            'terminal_id' => 'POS-1',
            'timestamp_ms' => 1700000000000,
            'session_uuid_v4' => '550e8400-e29b-41d4-a716-446655440000',
            'transaction_details' => [
                'total_amount_ngn' => 2500,
                'currency' => 'NGN',
            ],
            'account_info_public_display' => [
                'bank_name_hash' => 'sha256:abc',
            ],
        ];

        $sorted = $sort->invoke($controller, $payload);
        $json = $canonical->invoke($controller, $sorted);

        // Compact JSON (no spaces) — matches Python separators=(",", ":")
        $this->assertStringNotContainsString(': ', $json);
        $this->assertStringNotContainsString(', ', $json);

        $key = 'test-signing-key-16';
        $sig = base64_encode(hash_hmac('sha256', $json, $key, true));

        $this->assertTrue($verify->invoke($controller, $payload, $key, $sig));
        $this->assertFalse($verify->invoke($controller, $payload, $key, 'bad'));
    }
}
