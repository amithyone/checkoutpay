<?php

namespace Tests\Feature\Api;

use App\Models\VirtualCardRequest;
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
    }
}
