<?php

namespace Tests\Unit\Broadcast;

use App\Services\Broadcast\BroadcastSignatureVerifier;
use Tests\TestCase;

class BroadcastCanonicalJsonTest extends TestCase
{
    private BroadcastSignatureVerifier $signatures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signatures = new BroadcastSignatureVerifier;
    }

    public function test_hmac_matches_python_style_compact_sorted_json(): void
    {
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

        $sorted = $this->signatures->sortKeysRecursive($payload);
        $json = $this->signatures->canonicalJson($sorted);

        $this->assertStringNotContainsString(': ', $json);
        $this->assertStringNotContainsString(', ', $json);

        $key = 'test-signing-key-16';
        $sig = base64_encode(hash_hmac('sha256', $json, $key, true));

        $this->assertTrue($this->signatures->verifyHmacSha256($payload, $key, $sig));
        $this->assertFalse($this->signatures->verifyHmacSha256($payload, $key, 'bad'));
    }

    public function test_ed25519_sign_and_verify_round_trip(): void
    {
        $keypair = $this->signatures->generateEd25519Keypair();
        $payload = [
            'protocol_version' => 1,
            'timestamp_ms' => 1700000000000,
            'session_uuid_v4' => '550e8400-e29b-41d4-a716-446655440000',
            'terminal_id' => 'TERM-001',
            'transaction_details' => [
                'currency_code' => 'NGN',
                'total_amount_ngn' => 5000,
                'item_count' => 3,
            ],
            'account_info_public_display' => [
                'bank_name_hash' => 'sha256:abc',
                'masked_account_suffix' => '***1234',
            ],
        ];

        $signature = $this->signatures->signEd25519($payload, $keypair['signing_key']);

        $this->assertTrue($this->signatures->verify(
            $payload,
            'ed25519',
            $signature,
            '',
            $keypair['public_key'],
        ));
    }
}
