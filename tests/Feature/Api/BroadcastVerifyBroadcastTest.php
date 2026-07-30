<?php

namespace Tests\Feature\Api;

use App\Services\Broadcast\BroadcastSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BroadcastVerifyBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_broadcast_accepts_ed25519_packet(): void
    {
        $signatures = new BroadcastSignatureVerifier;
        $keypair = $signatures->generateEd25519Keypair();
        $bankName = 'CheckoutPay';
        $bankNameHash = 'sha256:'.hash('sha256', strtolower(trim($bankName)));

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'TERM-001',
            'merchant_id' => 'MCH-TERM-001',
            'api_key' => 'bk_test_api_key_123456789012345678901234',
            'signing_key' => '',
            'public_key' => $keypair['public_key'],
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Amithy Store',
            'bank_name' => $bankName,
            'bank_name_hash' => $bankNameHash,
            'masked_account_suffix' => '***1234',
            'account_number' => '0123456789',
            'recipient_bank_code' => '058',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'protocol_version' => 1,
            'timestamp_ms' => (int) (microtime(true) * 1000),
            'session_uuid_v4' => '550e8400-e29b-41d4-a716-446655440000',
            'terminal_id' => 'TERM-001',
            'transaction_details' => [
                'currency_code' => 'NGN',
                'total_amount_ngn' => 5000,
                'item_count' => 3,
            ],
            'account_info_public_display' => [
                'bank_name_hash' => $bankNameHash,
                'masked_account_suffix' => '***1234',
            ],
        ];

        $packet = [
            'payload' => $payload,
            'signature_alg' => 'ed25519',
            'signature' => $signatures->signEd25519($payload, $keypair['signing_key']),
        ];

        $response = $this->postJson('/api/v1/broadcast/verify-broadcast', $packet);

        $response->assertOk()
            ->assertJson([
                'valid' => true,
                'merchant_name' => 'Amithy Store',
                'amount_ngn' => 5000,
                'masked_account_suffix' => '***1234',
                'session_uuid' => '550e8400-e29b-41d4-a716-446655440000',
                'terminal_id' => 'TERM-001',
                'recipient_account' => '0123456789',
                'recipient_bank_code' => '058',
            ]);

        // App retry / double verify with the same signed packet should still succeed.
        $retry = $this->postJson('/api/v1/broadcast/verify-broadcast', $packet);
        $retry->assertOk()->assertJson(['valid' => true, 'amount_ngn' => 5000]);
    }

    public function test_failed_signature_does_not_block_retry_with_valid_packet(): void
    {
        $signatures = new BroadcastSignatureVerifier;
        $keypair = $signatures->generateEd25519Keypair();
        $bankName = 'CheckoutPay';
        $bankNameHash = 'sha256:'.hash('sha256', strtolower(trim($bankName)));

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'TERM-002',
            'merchant_id' => 'MCH-TERM-002',
            'api_key' => 'bk_test_api_key_123456789012345678901235',
            'signing_key' => '',
            'public_key' => $keypair['public_key'],
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Retry Shop',
            'bank_name' => $bankName,
            'bank_name_hash' => $bankNameHash,
            'masked_account_suffix' => '***5678',
            'account_number' => '0123456789',
            'recipient_bank_code' => '058',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'protocol_version' => 1,
            'timestamp_ms' => (int) (microtime(true) * 1000),
            'session_uuid_v4' => '660e8400-e29b-41d4-a716-446655440001',
            'terminal_id' => 'TERM-002',
            'transaction_details' => [
                'currency_code' => 'NGN',
                'total_amount_ngn' => 2500,
                'item_count' => 1,
            ],
            'account_info_public_display' => [
                'bank_name_hash' => $bankNameHash,
                'masked_account_suffix' => '***5678',
            ],
        ];

        $badPacket = [
            'payload' => $payload,
            'signature_alg' => 'ed25519',
            'signature' => 'invalid-signature',
        ];

        $this->postJson('/api/v1/broadcast/verify-broadcast', $badPacket)
            ->assertOk()
            ->assertJson(['valid' => false, 'error' => 'Invalid signature']);

        $goodPacket = [
            'payload' => $payload,
            'signature_alg' => 'ed25519',
            'signature' => $signatures->signEd25519($payload, $keypair['signing_key']),
        ];

        $this->postJson('/api/v1/broadcast/verify-broadcast', $goodPacket)
            ->assertOk()
            ->assertJson(['valid' => true, 'amount_ngn' => 2500]);
    }

    public function test_register_terminal_returns_checkoutnow_credentials(): void
    {
        config(['broadcast.admin_key' => 'test-admin-key']);

        $response = $this->postJson('/api/v1/broadcast/terminals/register', [
            'terminal_id' => 'POS-DEMO-002',
            'signature_alg' => 'ed25519',
            'merchant_name' => 'Demo Shop',
            'bank_name' => 'GTBank',
            'masked_account_suffix' => '***5678',
            'account_number' => '0123456789',
            'recipient_bank_code' => '058',
        ], [
            'X-Admin-Key' => 'test-admin-key',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'ok',
                'status',
                'terminal_id',
                'merchant_id',
                'api_key',
                'signature_alg',
                'signing_key',
            ])
            ->assertJson([
                'terminal_id' => 'POS-DEMO-002',
                'signature_alg' => 'ED25519',
            ]);

        $this->assertDatabaseHas('broadcast_terminals', [
            'terminal_id' => 'POS-DEMO-002',
            'merchant_name' => 'Demo Shop',
            'signature_alg' => 'ED25519',
        ]);
    }
}
