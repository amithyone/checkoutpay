<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\TouchConsumerAppSession;
use App\Models\ConsumerWalletApiAccount;
use App\Models\VirtualCardRequest;
use App\Models\VirtualCardRequestLog;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\VirtualCardRequestLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerVirtualCardRetrySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(TouchConsumerAppSession::class);
    }

    public function test_retry_sync_replays_stored_webhook_and_activates_preparing_card(): void
    {
        [$wallet, $account, $row] = $this->preparingWalletWithCard();
        $mevonReq = 'REQ1785348853524';
        $mevonUuid = '766f5cdb-9956-4cec-af77-b520f624acc3';
        $cardId = 'bab449bb-15e9-404a-aa73-657519df4794';

        $row->update([
            'status' => VirtualCardRequest::STATUS_PREPARING,
            'provider_reference' => $mevonReq,
            'card_name' => 'Miracle Oha',
            'response_payload' => [
                'status' => false,
                'message' => 'Card creation request processed successfully',
                'data' => ['request_id' => $mevonReq],
            ],
        ]);

        $payload = [
            'event' => 'card.created.success',
            'data' => [
                'request_id' => $mevonReq,
                'card_id' => $cardId,
                'card_brand' => 'visa',
                'card_type' => 'virtual',
                'card_name' => 'Miracle Oha',
                'card_number' => '4865550146451802',
                'last4' => '1802',
                'expiry' => '07/2029',
                'cvv' => '677',
                'balance' => 5,
                'reference' => $mevonUuid,
            ],
        ];

        $cardLogs = app(VirtualCardRequestLogService::class);
        VirtualCardRequestLog::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'level' => VirtualCardRequestLog::LEVEL_WARNING,
            'event' => 'webhook_no_match',
            'message' => 'Card webhook received but no virtual card request matched',
            'context' => $cardLogs->withMevonWebhook($payload, json_encode($payload)),
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/retry-sync');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.card_preparing', false);

        $row->refresh();
        $this->assertSame(VirtualCardRequest::STATUS_ACTIVE, $row->status);
        $this->assertSame($cardId, $row->card_external_id);
        $this->assertSame($mevonReq, $row->provider_reference);
    }

    public function test_retry_sync_returns_helpful_message_when_no_webhook_log_exists(): void
    {
        [, $account, $row] = $this->preparingWalletWithCard();
        $row->update([
            'status' => VirtualCardRequest::STATUS_PREPARING,
            'provider_reference' => 'REQ0000000001',
        ]);

        Sanctum::actingAs($account);

        $response = $this->postJson('/api/v1/consumer/cards/retry-sync');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No stored MevonPay card-created webhook found for this request yet. Try again in a minute, or contact support if it stays stuck.');
    }

    /**
     * @return array{0: WhatsappWallet, 1: ConsumerWalletApiAccount, 2: VirtualCardRequest}
     */
    private function preparingWalletWithCard(): array
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348148790554',
            'display_name' => 'Miracle Oha',
            'balance' => 30000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'kyc_fname' => 'Miracle',
            'kyc_lname' => 'Oha',
            'kyc_email' => 'miracle@example.com',
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        $row = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_PREPARING,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-RETRY-SYNC',
            'card_name' => 'Miracle Oha',
            'request_payload' => [
                'email' => 'miracle@example.com',
                'phoneNumber' => '08012345678',
                'cardName' => 'Miracle Oha',
            ],
        ]);

        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'sender_name' => $wallet->display_name,
            'type' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
            'amount' => $row->fee_ngn,
            'balance_after' => round((float) $wallet->balance - (float) $row->fee_ngn, 2),
            'external_reference' => $row->external_reference,
            'meta' => ['channel' => 'consumer_api'],
        ]);

        return [$wallet, $account, $row];
    }
}
