<?php

namespace Tests\Feature\Api;

use App\Models\VirtualCardRequest;
use App\Models\VirtualCardRequestLog;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MevonPayVirtualCardWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function createHeldFeeTransaction(WhatsappWallet $wallet, VirtualCardRequest $row): void
    {
        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'sender_name' => $wallet->display_name,
            'type' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
            'amount' => $row->fee_ngn,
            'balance_after' => round((float) $wallet->balance - (float) $row->fee_ngn, 2),
            'external_reference' => $row->external_reference,
            'meta' => ['channel' => 'consumer_api'],
        ]);
    }

    public function test_card_created_webhook_activates_preparing_request(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348148790554',
            'display_name' => 'Reviewer',
            'balance' => 30000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $row = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_PREPARING,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-WH-REF',
            'card_name' => 'Test User',
            'request_payload' => [
                'email' => 'test@example.com',
                'phoneNumber' => '08148790554',
            ],
        ]);
        $this->createHeldFeeTransaction($wallet, $row);

        $response = $this->postJson('/api/v1/webhook/mevonpay', [
            'event' => 'card.created',
            'data' => [
                'reference' => 'VCARD-WH-REF',
                'card_id' => 'MEVON-CARD-88',
                'email' => 'test@example.com',
            ],
        ]);

        $response->assertOk()->assertJsonPath('message', 'Virtual card activated');

        $row->refresh();
        $this->assertSame(VirtualCardRequest::STATUS_ACTIVE, $row->status);
        $this->assertSame('MEVON-CARD-88', $row->card_external_id);

        $received = VirtualCardRequestLog::query()->where('event', 'webhook_received')->first();
        $this->assertNotNull($received);
        $this->assertSame('card.created', data_get($received->context, 'raw_payload.event'));
        $this->assertNotEmpty(data_get($received->context, 'raw_body'));
    }

    public function test_card_created_success_with_mevon_uuid_reference_matches_preparing_request(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348148790554',
            'display_name' => 'Reviewer',
            'balance' => 30000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $mevonRef = '766f5cdb-9956-4cec-af77-b520f624acc3';
        $cardId = 'bab449bb-15e9-404a-aa73-657519df4794';

        $row = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_PREPARING,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-USER-REF-001',
            'provider_reference' => $mevonRef,
            'card_name' => 'Test User',
            'request_payload' => [
                'email' => 'reviewer@example.com',
                'phoneNumber' => '08148790554',
            ],
            'response_payload' => [
                'status' => false,
                'message' => 'Card creation request processed successfully',
                'data' => ['reference' => $mevonRef],
            ],
        ]);
        $this->createHeldFeeTransaction($wallet, $row);

        $response = $this->postJson('/api/v1/webhook/mevonpay', [
            'event' => 'card.created.success',
            'data' => [
                'reference' => $mevonRef,
                'card_id' => $cardId,
                'email' => 'reviewer@example.com',
                'card_number' => '4288520141503096',
                'last4' => '3096',
                'expiry' => '06/2029',
                'cvv' => '486',
                'balance' => 5,
                'card_name' => 'Test User',
            ],
        ]);

        $response->assertOk()->assertJsonPath('message', 'Virtual card activated');

        $row->refresh();
        $this->assertSame(VirtualCardRequest::STATUS_ACTIVE, $row->status);
        $this->assertSame($cardId, $row->card_external_id);
        $this->assertSame('4288520141503096', data_get($row->card_details_payload, 'card_number'));
        $this->assertSame('486', data_get($row->card_details_payload, 'cvv'));
        $this->assertSame(5.0, (float) $row->card_balance_usd);
    }

    public function test_card_created_success_recovers_recent_failed_request_by_email(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345678',
            'display_name' => 'Failed User',
            'balance' => 50000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $row = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_FAILED,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-FAILED-001',
            'failure_reason' => 'Card creation request processed successfully',
            'created_at' => now()->subHours(2),
            'request_payload' => [
                'email' => 'failed@example.com',
                'phoneNumber' => '08012345678',
            ],
        ]);

        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'sender_name' => 'Failed User',
            'type' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
            'amount' => 6925,
            'balance_after' => 43075,
            'external_reference' => $row->external_reference,
            'meta' => [
                'refunded' => true,
                'refund_reason' => 'provider_failed',
            ],
        ]);

        $response = $this->postJson('/api/v1/webhook/mevonpay', [
            'event' => 'card.created.success',
            'data' => [
                'reference' => '766f5cdb-9956-4cec-af77-b520f624acc3',
                'card_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
                'email' => 'failed@example.com',
            ],
        ]);

        $response->assertOk()->assertJsonPath('message', 'Virtual card activated');

        $row->refresh();
        $wallet->refresh();
        $this->assertSame(VirtualCardRequest::STATUS_ACTIVE, $row->status);
        $this->assertSame('bab449bb-15e9-404a-aa73-657519df4794', $row->card_external_id);
        $this->assertSame(43075.0, (float) $wallet->balance);

        $this->assertDatabaseHas('virtual_card_request_logs', [
            'virtual_card_request_id' => $row->id,
            'event' => 'webhook_fee_recollected',
        ]);
    }

    public function test_webhook_activation_supersedes_other_failed_attempts_for_wallet(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348011112222',
            'display_name' => 'Multi Attempt',
            'balance' => 50000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $failedNewer = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_FAILED,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-FAILED-NEWER',
            'failure_reason' => 'provider timeout',
            'request_payload' => [
                'email' => 'multi@example.com',
                'phoneNumber' => '08011112222',
            ],
        ]);

        $winner = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_FAILED,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-WINNER-OLD',
            'failure_reason' => 'Card creation request processed successfully',
            'created_at' => now()->subHours(3),
            'request_payload' => [
                'email' => 'multi@example.com',
                'phoneNumber' => '08011112222',
            ],
        ]);

        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'sender_name' => 'Multi Attempt',
            'type' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
            'amount' => 6925,
            'balance_after' => 43075,
            'external_reference' => $winner->external_reference,
            'meta' => [
                'refunded' => true,
                'refund_reason' => 'provider_failed',
            ],
        ]);

        $response = $this->postJson('/api/v1/webhook/mevonpay', [
            'event' => 'card.created.success',
            'data' => [
                'reference' => '766f5cdb-9956-4cec-af77-b520f624acc3',
                'card_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
                'email' => 'multi@example.com',
            ],
        ]);

        $response->assertOk()->assertJsonPath('message', 'Virtual card activated');

        $failedNewer->refresh();
        $winner->refresh();

        $this->assertSame(VirtualCardRequest::STATUS_ACTIVE, $winner->status);
        $this->assertStringContainsString('Superseded', (string) $failedNewer->failure_reason);
        $this->assertSame(VirtualCardRequest::STATUS_FAILED, $failedNewer->status);
    }

    public function test_card_created_success_with_mevon_req_reference_matches_preparing_request(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348099988776',
            'display_name' => 'Req Ref User',
            'balance' => 30000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $mevonReq = 'REQ1780744493644';
        $cardId = 'bab449bb-15e9-404a-aa73-657519df4794';

        $row = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_PREPARING,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-REQ-REF-001',
            'provider_reference' => $mevonReq,
            'card_name' => 'Req User',
            'request_payload' => [
                'email' => 'requser@example.com',
                'phoneNumber' => '08099988776',
            ],
            'response_payload' => [
                'status' => false,
                'message' => 'Card creation request processed successfully',
                'data' => ['request_id' => $mevonReq],
            ],
        ]);
        $this->createHeldFeeTransaction($wallet, $row);

        $response = $this->postJson('/api/v1/webhook/mevonpay', [
            'event' => 'card.created.success',
            'request_id' => $mevonReq,
            'data' => [
                'card_id' => $cardId,
                'email' => 'requser@example.com',
            ],
        ]);

        $response->assertOk()->assertJsonPath('message', 'Virtual card activated');

        $row->refresh();
        $this->assertSame(VirtualCardRequest::STATUS_ACTIVE, $row->status);
        $this->assertSame($cardId, $row->card_external_id);
        $this->assertSame($mevonReq, $row->provider_reference);
    }

    public function test_card_created_success_webhook_persists_request_id_and_balance_from_full_payload(): void
    {
        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'test-secret',
        ]);

        Http::fake([
            'https://mevon.test/V1/card_balance' => Http::response([
                'success' => true,
                'message' => 'Card balance updated successfully',
                'data' => ['balance' => 10, 'currency' => 'USD'],
            ], 200),
            'https://mevon.test/V1/card_details' => Http::response([
                'success' => true,
                'data' => [
                    'card_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
                    'card_code' => 'VCARD2026060611150700359',
                    'card_number' => '4288520141503096',
                    'cvv' => '486',
                    'expiry_month_year' => '06/29',
                    'balance' => 10,
                ],
            ], 200),
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348148790554',
            'display_name' => 'innocent Solomon',
            'balance' => 30000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $row = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_PREPARING,
            'fee_usd' => 5,
            'fee_ngn' => 6915.65,
            'external_reference' => 'VCARD-REF-INNOCENT',
            'card_name' => 'innocent Solomon',
            'request_payload' => [
                'email' => 'amithyone@gmail.com',
                'phoneNumber' => '08148790554',
            ],
        ]);
        $this->createHeldFeeTransaction($wallet, $row);

        $response = $this->postJson('/api/v1/webhook/mevonpay', [
            'event' => 'card.created.success',
            'data' => [
                'request_id' => 'REQ1780744493644',
                'card_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
                'card_brand' => 'visa',
                'card_type' => 'virtual',
                'card_name' => 'innocent Solomon',
                'card_number' => '4288520141503096',
                'last4' => '3096',
                'expiry' => '06/2029',
                'cvv' => '486',
                'balance' => 5,
                'reference' => '766f5cdb-9956-4cec-af77-b520f624acc3',
            ],
        ]);

        $response->assertOk()->assertJsonPath('message', 'Virtual card activated');

        $row->refresh();
        $this->assertSame('REQ1780744493644', $row->provider_reference);
        $this->assertSame(10.0, (float) $row->card_balance_usd);
    }

    public function test_card_webhook_no_match_returns_clear_message_not_ignored(): void
    {
        $response = $this->postJson('/api/v1/webhook/mevonpay', [
            'event' => 'card.created.success',
            'data' => [
                'reference' => '766f5cdb-9956-4cec-af77-b520f624acc3',
                'card_id' => 'bab449bb-15e9-404a-aa73-657519df4794',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Card webhook received; no matching virtual card request found');
    }

    public function test_card_topup_success_webhook_updates_balance_once(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348148790554',
            'display_name' => 'Reviewer',
            'balance' => 30000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $card = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_ACTIVE,
            'card_external_id' => 'VCARD2026060611150700359',
            'fee_usd' => 5,
            'fee_ngn' => 8000,
            'card_balance_usd' => 10,
        ]);

        $payload = [
            'event' => 'card.topup.success',
            'data' => [
                'card_code' => 'VCARD2026060611150700359',
                'last4' => '3096',
                'new_balance' => 138,
                'reference' => '766f5cdb-9956-4cec-af77-b520f624acc3',
                'timestamp' => '2026-06-10T10:39:29+01:00',
            ],
        ];

        // 1. Process webhook first time
        $response = $this->postJson('/api/v1/webhook/mevonpay', $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Card topup processed');

        $card->refresh();
        $this->assertSame(138.0, (float) $card->card_balance_usd);

        // Modify balance locally to verify duplicate webhook is ignored and doesn't reset it
        $card->update(['card_balance_usd' => 200]);

        // 2. Process webhook second time
        $response = $this->postJson('/api/v1/webhook/mevonpay', $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Card topup processed');

        $card->refresh();
        $this->assertSame(200.0, (float) $card->card_balance_usd); // balance remained 200, webhook ignored
    }

    public function test_duplicate_card_spend_webhook_is_processed_once(): void
    {
        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'test-secret',
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348148790554',
            'display_name' => 'Reviewer',
            'balance' => 30000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $card = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_ACTIVE,
            'card_external_id' => 'VCARD2026060611150700359',
            'fee_usd' => 5,
            'fee_ngn' => 8000,
            'card_balance_usd' => 138,
            'request_payload' => ['amount' => 138],
            'card_details_payload' => [
                'card_code' => 'VCARD2026060611150700359',
            ],
        ]);

        Http::fake([
            'https://mevon.test/V1/card_balance' => Http::response([
                'status' => 'success',
                'data' => ['balance' => 138],
            ], 200),
            'https://mevon.test/V1/card_transactions' => Http::response([
                'status' => 'success',
                'data' => [[
                    'code' => 'TXN-SPEND-1',
                    'reference' => 'ref-spend-1',
                    'drcr' => 'DR',
                    'amount' => 37.0,
                    'status' => 'completed',
                    'category' => 'Card Purchase',
                    'description' => 'Netflix.com',
                    'createdOn' => '2026-06-10T10:39:29+01:00',
                ]],
            ], 200),
        ]);

        $payload = [
            'event' => 'card.spend.success',
            'data' => [
                'card_code' => 'VCARD2026060611150700359',
                'code' => 'TXN-SPEND-1',
                'reference' => 'ref-spend-1',
                'amount' => 37.0,
                'drcr' => 'DR',
                'category' => 'Card Purchase',
                'description' => 'Netflix.com',
                'balance' => 138,
                'createdOn' => '2026-06-10T10:39:29+01:00',
            ],
        ];

        $this->postJson('/api/v1/webhook/mevonpay', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Card spend processed');

        $card->refresh();
        $this->assertSame(101.0, (float) $card->card_balance_usd);

        $card->update(['card_balance_usd' => 999]);

        $this->postJson('/api/v1/webhook/mevonpay', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Card spend processed');

        $this->assertSame(999.0, (float) $card->fresh()->card_balance_usd);
    }
}
