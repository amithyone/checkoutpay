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
                'total_amount_ngn' => 500_000,
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
                'recipient_account_name' => 'Amithy Store',
                'amount_ngn' => 5000,
                'bank_name' => 'CheckoutPay',
                'masked_account_suffix' => '***1234',
                'session_uuid' => '550e8400-e29b-41d4-a716-446655440000',
                'session_status' => 'open',
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
                'total_amount_ngn' => 250_000,
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

    public function test_verify_broadcast_rejects_missing_timestamp_ms(): void
    {
        $signatures = new BroadcastSignatureVerifier;
        $keypair = $signatures->generateEd25519Keypair();
        $bankName = 'CheckoutPay';
        $bankNameHash = 'sha256:'.hash('sha256', strtolower(trim($bankName)));

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'TERM-NO-TS',
            'merchant_id' => 'MCH-TERM-NO-TS',
            'api_key' => 'bk_test_api_key_123456789012345678901236',
            'signing_key' => '',
            'public_key' => $keypair['public_key'],
            'signature_alg' => 'ED25519',
            'merchant_name' => 'No Timestamp Shop',
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
            'session_uuid_v4' => '770e8400-e29b-41d4-a716-446655440002',
            'terminal_id' => 'TERM-NO-TS',
            'transaction_details' => [
                'currency_code' => 'NGN',
                'total_amount_ngn' => 100_000,
                'item_count' => 1,
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

        $this->postJson('/api/v1/broadcast/verify-broadcast', $packet)
            ->assertOk()
            ->assertJson(['valid' => false, 'error' => 'Missing timestamp_ms in payload']);
    }

    public function test_verify_broadcast_ignores_bank_name_hash_mismatch_when_signature_valid(): void
    {
        $signatures = new BroadcastSignatureVerifier;
        $keypair = $signatures->generateEd25519Keypair();
        $expectedBankName = 'RUBIES MFB';
        $expectedBankNameHash = 'sha256:'.hash('sha256', strtolower(trim($expectedBankName)));
        $wrongBankNameHash = 'sha256:'.hash('sha256', 'totally-wrong-bank-name');

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'TERM-HASH',
            'merchant_id' => 'MCH-TERM-HASH',
            'api_key' => 'bk_test_api_key_123456789012345678901237',
            'signing_key' => '',
            'public_key' => $keypair['public_key'],
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Hash Test Shop',
            'bank_name' => $expectedBankName,
            'bank_name_hash' => $expectedBankNameHash,
            'masked_account_suffix' => '***4863',
            'account_number' => '1000004863',
            'recipient_bank_code' => '090175',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'protocol_version' => 2.0,
            'timestamp_ms' => (int) (microtime(true) * 1000),
            'session_uuid_v4' => '880e8400-e29b-41d4-a716-446655440003',
            'terminal_id' => 'TERM-HASH',
            'transaction_details' => [
                'currency_code' => 'NGN',
                'total_amount_ngn' => 1,
                'item_count' => 1,
            ],
            'account_info_public_display' => [
                'bank_name_hash' => $wrongBankNameHash,
                'masked_account_suffix' => '***9876',
            ],
        ];

        $packet = [
            'payload' => $payload,
            'signature_alg' => 'ed25519',
            'signature' => $signatures->signEd25519($payload, $keypair['signing_key']),
        ];

        $this->postJson('/api/v1/broadcast/verify-broadcast', $packet)
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'bank_name' => 'RUBIES MFB',
                'masked_account_suffix' => '***4863',
                'recipient_account' => '1000004863',
            ]);
    }

    public function test_verify_broadcast_accepts_checkoutpay_slug_for_rubies_terminal(): void
    {
        $signatures = new BroadcastSignatureVerifier;
        $keypair = $signatures->generateEd25519Keypair();
        $expectedBankName = 'RUBIES MFB';
        $expectedBankNameHash = 'sha256:'.hash('sha256', strtolower(trim($expectedBankName)));
        $checkoutPayHash = 'sha256:'.hash('sha256', 'checkoutpay');

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'CP-TEST',
            'merchant_id' => 'MCH-CP-TEST',
            'api_key' => 'bk_test_api_key_123456789012345678901241',
            'signing_key' => '',
            'public_key' => $keypair['public_key'],
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Slug Test Shop',
            'bank_name' => $expectedBankName,
            'bank_name_hash' => $expectedBankNameHash,
            'masked_account_suffix' => '***4863',
            'account_number' => '1000004863',
            'recipient_bank_code' => '090175',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'protocol_version' => 2.0,
            'timestamp_ms' => (int) (microtime(true) * 1000),
            'session_uuid_v4' => 'cc0e8400-e29b-41d4-a716-446655440007',
            'terminal_id' => 'CP-TEST',
            'transaction_details' => [
                'currency_code' => 'NGN',
                'total_amount_ngn' => 1,
                'item_count' => 1,
            ],
            'account_info_public_display' => [
                'bank_name_hash' => $checkoutPayHash,
                'masked_account_suffix' => '***4863',
            ],
        ];

        $packet = [
            'payload' => $payload,
            'signature_alg' => 'ed25519',
            'signature' => $signatures->signEd25519($payload, $keypair['signing_key']),
        ];

        $this->postJson('/api/v1/broadcast/verify-broadcast', $packet)
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'bank_name' => 'RUBIES MFB',
                'session_status' => 'open',
            ]);
    }

    public function test_verify_broadcast_accepts_kuda_slug_for_rubies_cp_terminal(): void
    {
        $signatures = new BroadcastSignatureVerifier;
        $keypair = $signatures->generateEd25519Keypair();
        $kudaHash = 'sha256:'.hash('sha256', 'kuda');

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'CP-KUDA',
            'merchant_id' => 'MCH-CP-KUDA',
            'api_key' => 'bk_test_api_key_123456789012345678901242',
            'signing_key' => '',
            'public_key' => $keypair['public_key'],
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Kuda Slug Shop',
            'bank_name' => 'RUBIES MFB',
            'bank_name_hash' => 'sha256:'.hash('sha256', 'rubies mfb'),
            'masked_account_suffix' => '***4863',
            'account_number' => '1000004863',
            'recipient_bank_code' => '090175',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'protocol_version' => 2.0,
            'timestamp_ms' => (int) (microtime(true) * 1000),
            'session_uuid_v4' => 'dd0e8400-e29b-41d4-a716-446655440008',
            'terminal_id' => 'CP-KUDA',
            'transaction_details' => [
                'currency_code' => 'NGN',
                'total_amount_ngn' => 1,
                'item_count' => 1,
            ],
            'account_info_public_display' => [
                'bank_name_hash' => $kudaHash,
                'masked_account_suffix' => '***9876',
            ],
        ];

        $packet = [
            'payload' => $payload,
            'signature_alg' => 'ed25519',
            'signature' => $signatures->signEd25519($payload, $keypair['signing_key']),
        ];

        $this->postJson('/api/v1/broadcast/verify-broadcast', $packet)
            ->assertOk()
            ->assertJson(['valid' => true, 'bank_name' => 'RUBIES MFB']);
    }

    public function test_open_session_accepts_stale_timestamp_on_retry(): void
    {
        $signatures = new BroadcastSignatureVerifier;
        $keypair = $signatures->generateEd25519Keypair();
        $bankName = 'CheckoutPay';
        $bankNameHash = 'sha256:'.hash('sha256', strtolower(trim($bankName)));
        $sessionUuid = '990e8400-e29b-41d4-a716-446655440004';

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'TERM-01',
            'merchant_id' => 'MCH-TERM-01',
            'api_key' => 'bk_test_api_key_123456789012345678901238',
            'signing_key' => '',
            'public_key' => $keypair['public_key'],
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Session Shop',
            'bank_name' => $bankName,
            'bank_name_hash' => $bankNameHash,
            'masked_account_suffix' => '***1234',
            'account_number' => '0123456789',
            'recipient_bank_code' => '058',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $freshPayload = [
            'protocol_version' => 2.0,
            'timestamp_ms' => (int) (microtime(true) * 1000),
            'session_uuid_v4' => $sessionUuid,
            'terminal_id' => 'TERM-01',
            'transaction_details' => [
                'currency_code' => 'NGN',
                'total_amount_ngn' => 150_000,
                'item_count' => 2,
            ],
            'account_info_public_display' => [
                'bank_name_hash' => $bankNameHash,
                'masked_account_suffix' => '***1234',
            ],
        ];

        $freshPacket = [
            'payload' => $freshPayload,
            'signature_alg' => 'ed25519',
            'signature' => $signatures->signEd25519($freshPayload, $keypair['signing_key']),
        ];

        $this->postJson('/api/v1/broadcast/verify-broadcast', $freshPacket)
            ->assertOk()
            ->assertJson(['valid' => true, 'session_status' => 'open']);

        $stalePayload = $freshPayload;
        $stalePayload['timestamp_ms'] = (int) (microtime(true) * 1000) - (15 * 60 * 1000);
        $stalePacket = [
            'payload' => $stalePayload,
            'signature_alg' => 'ed25519',
            'signature' => $signatures->signEd25519($stalePayload, $keypair['signing_key']),
        ];

        $this->postJson('/api/v1/broadcast/verify-broadcast', $stalePacket)
            ->assertOk()
            ->assertJson(['valid' => true, 'session_status' => 'open', 'session_uuid' => $sessionUuid]);
    }

    public function test_verify_broadcast_rejects_paid_session(): void
    {
        $signatures = new BroadcastSignatureVerifier;
        $keypair = $signatures->generateEd25519Keypair();
        $bankName = 'CheckoutPay';
        $bankNameHash = 'sha256:'.hash('sha256', strtolower(trim($bankName)));
        $sessionUuid = 'aa0e8400-e29b-41d4-a716-446655440005';

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'TERM-02',
            'merchant_id' => 'MCH-TERM-02',
            'api_key' => 'bk_test_api_key_123456789012345678901239',
            'signing_key' => '',
            'public_key' => $keypair['public_key'],
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Paid Shop',
            'bank_name' => $bankName,
            'bank_name_hash' => $bankNameHash,
            'masked_account_suffix' => '***1234',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('broadcast_sessions')->insert([
            'session_uuid' => $sessionUuid,
            'terminal_id' => 'TERM-02',
            'status' => 'paid',
            'amount_ngn' => 500,
            'opened_at' => (int) (microtime(true) * 1000),
            'closed_at' => (int) (microtime(true) * 1000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'protocol_version' => 2.0,
            'timestamp_ms' => (int) (microtime(true) * 1000),
            'session_uuid_v4' => $sessionUuid,
            'terminal_id' => 'TERM-02',
            'transaction_details' => [
                'currency_code' => 'NGN',
                'total_amount_ngn' => 50_000,
                'item_count' => 1,
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

        $this->postJson('/api/v1/broadcast/verify-broadcast', $packet)
            ->assertOk()
            ->assertJson([
                'valid' => false,
                'error' => 'Session already paid',
                'session_status' => 'paid',
            ]);
    }

    public function test_cancel_session_marks_session_cancelled(): void
    {
        $sessionUuid = 'bb0e8400-e29b-41d4-a716-446655440006';

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'TERM-03',
            'merchant_id' => 'MCH-TERM-03',
            'api_key' => 'bk_test_api_key_123456789012345678901240',
            'signing_key' => 'test-signing-key-min-16-chars',
            'signature_alg' => 'HMAC-SHA256',
            'merchant_name' => 'Cancel Shop',
            'bank_name' => 'GTBank',
            'bank_name_hash' => 'sha256:abc',
            'masked_account_suffix' => '***9999',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('broadcast_sessions')->insert([
            'session_uuid' => $sessionUuid,
            'terminal_id' => 'TERM-03',
            'status' => 'open',
            'amount_ngn' => 100,
            'opened_at' => (int) (microtime(true) * 1000),
            'closed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/broadcast/sessions/cancel', [
            'session_uuid_v4' => $sessionUuid,
            'terminal_id' => 'TERM-03',
        ])->assertOk()->assertJson(['ok' => true, 'session_status' => 'cancelled']);

        $this->assertDatabaseHas('broadcast_sessions', [
            'session_uuid' => $sessionUuid,
            'status' => 'cancelled',
        ]);
    }

    public function test_verify_broadcast_accepts_online_protocol_without_account_display(): void
    {
        $signatures = new BroadcastSignatureVerifier;
        $keypair = $signatures->generateEd25519Keypair();

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'CP-ONLINE',
            'merchant_id' => 'MCH-CP-ONLINE',
            'api_key' => 'bk_test_api_key_online_protocol_v21',
            'signing_key' => '',
            'public_key' => $keypair['public_key'],
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Online Mode Shop',
            'bank_name' => 'RUBIES MFB',
            'bank_name_hash' => 'sha256:'.hash('sha256', 'rubies mfb'),
            'masked_account_suffix' => '***4863',
            'account_number' => '1000004863',
            'recipient_bank_code' => '090175',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'protocol_version' => 2.1,
            'connectivity' => 'online',
            'timestamp_ms' => (int) (microtime(true) * 1000),
            'session_uuid_v4' => '990e8400-e29b-41d4-a716-446655440011',
            'terminal_id' => 'CP-ONLINE',
            'transaction_details' => [
                'currency_code' => 'NGN',
                'total_amount_ngn' => 250_000,
                'item_count' => 2,
            ],
        ];

        $packet = [
            'payload' => $payload,
            'signature_alg' => 'ed25519',
            'signature' => $signatures->signEd25519($payload, $keypair['signing_key']),
        ];

        $this->postJson('/api/v1/broadcast/verify-broadcast', $packet)
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'connectivity' => 'online',
                'terminal_id' => 'CP-ONLINE',
                'terminal_label' => 'CP-ONLINE',
                'amount_ngn' => 2500,
                'recipient_account' => '1000004863',
                'bank_name' => 'RUBIES MFB',
            ]);
    }

    public function test_verify_broadcast_cp_terminal_label_and_kobo_amount(): void
    {
        $signatures = new BroadcastSignatureVerifier;
        $keypair = $signatures->generateEd25519Keypair();

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'CP-1RK8Z',
            'merchant_id' => 'MCH-CP-1RK8Z',
            'api_key' => 'bk_test_api_key_cp_1rk8z',
            'signing_key' => '',
            'public_key' => $keypair['public_key'],
            'signature_alg' => 'ED25519',
            'merchant_name' => 'MIDAS AGRO',
            'bank_name' => 'RUBIES MFB',
            'bank_name_hash' => 'sha256:'.hash('sha256', 'rubies mfb'),
            'masked_account_suffix' => '***4863',
            'account_number' => '1000004863',
            'recipient_bank_code' => '090175',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'protocol_version' => 2.1,
            'connectivity' => 'online',
            'timestamp_ms' => (int) (microtime(true) * 1000),
            'session_uuid_v4' => '110e8400-e29b-41d4-a716-446655440012',
            'terminal_id' => 'CP-1RK8Z',
            'transaction_details' => [
                'currency_code' => 'NGN',
                'total_amount_ngn' => 900_376,
                'item_count' => 1,
            ],
        ];

        $packet = [
            'payload' => $payload,
            'signature_alg' => 'ed25519',
            'signature' => $signatures->signEd25519($payload, $keypair['signing_key']),
        ];

        $this->postJson('/api/v1/broadcast/verify-broadcast', $packet)
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'merchant_name' => 'MIDAS AGRO',
                'terminal_id' => 'CP-1RK8Z',
                'terminal_label' => 'CP-1RK8Z',
                'amount_ngn' => 9003.76,
            ]);
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

    public function test_session_status_returns_awaiting_scan_before_verify(): void
    {
        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'TERM-POLL',
            'merchant_id' => 'MCH-TERM-POLL',
            'api_key' => 'bk_test_api_key_poll_session_status',
            'signing_key' => '',
            'public_key' => 'dummy',
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Poll Shop',
            'bank_name' => 'GTBank',
            'bank_name_hash' => 'sha256:abc',
            'masked_account_suffix' => '***1111',
            'account_number' => '0123456789',
            'recipient_bank_code' => '058',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sessionUuid = 'ee0e8400-e29b-41d4-a716-446655440009';

        $this->getJson("/api/v1/broadcast/sessions/{$sessionUuid}?terminal_id=TERM-POLL", [
            'X-Terminal-Api-Key' => 'bk_test_api_key_poll_session_status',
        ])->assertOk()->assertJson([
            'session_uuid' => $sessionUuid,
            'session_status' => 'awaiting_scan',
            'terminal_id' => 'TERM-POLL',
            'merchant_name' => 'Poll Shop',
        ]);
    }

    public function test_session_status_returns_paid_with_settlement_details(): void
    {
        $sessionUuid = 'ff0e8400-e29b-41d4-a716-446655440010';
        $paidAt = (int) (microtime(true) * 1000);

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'TERM-PAID',
            'merchant_id' => 'MCH-TERM-PAID',
            'api_key' => 'bk_test_api_key_paid_session_status',
            'signing_key' => '',
            'public_key' => 'dummy',
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Paid Poll Shop',
            'bank_name' => 'RUBIES MFB',
            'bank_name_hash' => 'sha256:'.hash('sha256', 'rubies mfb'),
            'masked_account_suffix' => '***4863',
            'account_number' => '1000004863',
            'recipient_bank_code' => '090175',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('broadcast_sessions')->insert([
            'session_uuid' => $sessionUuid,
            'terminal_id' => 'TERM-PAID',
            'status' => 'paid',
            'amount_ngn' => 2500,
            'opened_at' => $paidAt - 60000,
            'closed_at' => $paidAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/broadcast/sessions/{$sessionUuid}?terminal_id=TERM-PAID", [
            'X-Terminal-Api-Key' => 'bk_test_api_key_paid_session_status',
        ])->assertOk()->assertJson([
            'session_uuid' => $sessionUuid,
            'session_status' => 'paid',
            'amount_ngn' => 2500,
            'terminal_id' => 'TERM-PAID',
            'merchant_name' => 'Paid Poll Shop',
            'recipient_account' => '1000004863',
            'recipient_account_name' => 'Paid Poll Shop',
            'recipient_bank_code' => '090175',
            'paid_at_ms' => $paidAt,
        ]);
    }

    public function test_session_status_rejects_wrong_api_key(): void
    {
        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'TERM-AUTH',
            'merchant_id' => 'MCH-TERM-AUTH',
            'api_key' => 'bk_test_api_key_auth_session',
            'signing_key' => '',
            'public_key' => 'dummy',
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Auth Shop',
            'bank_name' => 'GTBank',
            'bank_name_hash' => 'sha256:abc',
            'masked_account_suffix' => '***2222',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/broadcast/sessions/550e8400-e29b-41d4-a716-446655440000?terminal_id=TERM-AUTH', [
            'X-Terminal-Api-Key' => 'wrong-key',
        ])->assertUnauthorized();
    }

    public function test_sync_signing_key_aligns_public_key_for_verify(): void
    {
        $signatures = new BroadcastSignatureVerifier;
        $posKeypair = $signatures->generateEd25519Keypair();
        $wrongKeypair = $signatures->generateEd25519Keypair();

        DB::table('broadcast_terminals')->insert([
            'terminal_id' => 'CP-SYNC',
            'merchant_id' => 'MCH-CP-SYNC',
            'api_key' => 'bk_test_api_key_sync_signing_key',
            'signing_key' => '',
            'public_key' => $wrongKeypair['public_key'],
            'signature_alg' => 'ED25519',
            'merchant_name' => 'Sync Shop',
            'bank_name' => 'RUBIES MFB',
            'bank_name_hash' => 'sha256:'.hash('sha256', 'rubies mfb'),
            'masked_account_suffix' => '***4863',
            'account_number' => '1000004863',
            'recipient_bank_code' => '090175',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'protocol_version' => 2.1,
            'connectivity' => 'online',
            'timestamp_ms' => (int) (microtime(true) * 1000),
            'session_uuid_v4' => '220e8400-e29b-41d4-a716-446655440013',
            'terminal_id' => 'CP-SYNC',
            'transaction_details' => [
                'currency_code' => 'NGN',
                'total_amount_ngn' => 100_000,
                'item_count' => 1,
            ],
        ];

        $packet = [
            'payload' => $payload,
            'signature_alg' => 'ed25519',
            'signature' => $signatures->signEd25519($payload, $posKeypair['signing_key']),
        ];

        $this->postJson('/api/v1/broadcast/verify-broadcast', $packet)
            ->assertOk()
            ->assertJson(['valid' => false, 'error' => 'Invalid signature']);

        $this->postJson('/api/v1/broadcast/terminals/sync-signing-key', [
            'terminal_id' => 'CP-SYNC',
            'signing_key' => $posKeypair['signing_key'],
        ], [
            'X-Terminal-Api-Key' => 'bk_test_api_key_sync_signing_key',
        ])->assertOk()->assertJson(['ok' => true, 'terminal_id' => 'CP-SYNC']);

        $this->postJson('/api/v1/broadcast/verify-broadcast', $packet)
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'terminal_id' => 'CP-SYNC',
                'amount_ngn' => 1000,
            ]);
    }
}
