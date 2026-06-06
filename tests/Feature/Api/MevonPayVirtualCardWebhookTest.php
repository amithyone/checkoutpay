<?php

namespace Tests\Feature\Api;

use App\Models\VirtualCardRequest;
use App\Models\VirtualCardRequestLog;
use App\Models\WhatsappWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MevonPayVirtualCardWebhookTest extends TestCase
{
    use RefreshDatabase;

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

        $response = $this->postJson('/api/v1/webhook/mevonpay', [
            'event' => 'card.created.success',
            'data' => [
                'reference' => $mevonRef,
                'card_id' => $cardId,
                'email' => 'reviewer@example.com',
            ],
        ]);

        $response->assertOk()->assertJsonPath('message', 'Virtual card activated');

        $row->refresh();
        $this->assertSame(VirtualCardRequest::STATUS_ACTIVE, $row->status);
        $this->assertSame($cardId, $row->card_external_id);
    }

    public function test_card_created_success_recovers_recent_failed_request_by_email(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345678',
            'display_name' => 'Failed User',
            'balance' => 30000,
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
        $this->assertSame(VirtualCardRequest::STATUS_ACTIVE, $row->status);
        $this->assertSame('bab449bb-15e9-404a-aa73-657519df4794', $row->card_external_id);
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
}
