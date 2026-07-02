<?php

namespace Tests\Feature\Api;

use App\Models\VirtualCardRequest;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\VirtualCard\VirtualCardProviderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashwyreVirtualCardWebhookTest extends TestCase
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

    public function test_cashwyre_created_success_webhook_activates_preparing_request(): void
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
            'provider' => VirtualCardProviderResolver::PROVIDER_CASHWYRE,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-CWY-REF',
            'card_name' => 'Test User',
            'request_payload' => [
                'email' => 'test@example.com',
                'phoneNumber' => '08148790554',
            ],
        ]);
        $this->createHeldFeeTransaction($wallet, $row);

        $response = $this->postJson('/api/v1/webhook/cashwyre', [
            'eventType' => 'virtualcard.created.success',
            'eventData' => [
                'cardCode' => 'VCARD2024121622195100121',
                'Reference' => 'VCARD-CWY-REF',
                'CustomerEmail' => 'test@example.com',
                'CardNumber' => '4519460050119928',
                'CVV2' => '014',
                'ValidMonthYear' => '12/27',
                'CardBalance' => 25,
            ],
        ]);

        $response->assertOk()->assertJsonPath('message', 'Virtual card activated');

        $row->refresh();
        $this->assertSame(VirtualCardRequest::STATUS_ACTIVE, $row->status);
        $this->assertSame('VCARD2024121622195100121', $row->card_external_id);
    }

    public function test_cashwyre_topup_success_webhook_updates_balance(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348148790554',
            'display_name' => 'Reviewer',
            'balance' => 30000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $row = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_ACTIVE,
            'provider' => VirtualCardProviderResolver::PROVIDER_CASHWYRE,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-CWY-REF-2',
            'card_external_id' => 'VCARD2024121622195100121',
            'card_balance_usd' => 10,
            'card_name' => 'Test User',
        ]);

        $response = $this->postJson('/api/v1/webhook/cashwyre', [
            'eventType' => 'virtualcard.topup.success',
            'eventData' => [
                'cardCode' => 'VCARD2024121622195100121',
                'Reference' => 'topup-ref-1',
                'CardBalance' => 35,
            ],
        ]);

        $response->assertOk()->assertJsonPath('message', 'Card topup processed');
        $this->assertSame(35.0, (float) $row->fresh()->card_balance_usd);
    }
}
