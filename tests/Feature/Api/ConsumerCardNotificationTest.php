<?php

namespace Tests\Feature\Api;

use App\Mail\VirtualCardReadyMail;
use App\Mail\VirtualCardTransactionMail;
use App\Models\ConsumerWalletApiAccount;
use App\Models\VirtualCardRequest;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ConsumerCardNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.evolution.instance' => 'test-instance',
            'whatsapp.evolution.base_url' => 'https://evolution.test',
            'whatsapp.evolution.api_key' => 'test-key',
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'test-secret',
        ]);

        $mock = Mockery::mock(EvolutionWhatsAppClient::class);
        $mock->shouldReceive('sendText')->andReturn(true);
        $this->app->instance(EvolutionWhatsAppClient::class, $mock);
    }

    public function test_card_created_webhook_sends_email_and_whatsapp_when_enabled(): void
    {
        Mail::fake();

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348148790554',
            'balance' => 30000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'kyc_email' => 'carduser@example.com',
            'notify_card_created_email' => true,
            'notify_card_created_whatsapp' => true,
        ]);

        $row = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_PREPARING,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-NOTIFY-REF',
            'card_name' => 'Notify User',
        ]);

        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
            'amount' => $row->fee_ngn,
            'balance_after' => round((float) $wallet->balance - (float) $row->fee_ngn, 2),
            'external_reference' => $row->external_reference,
            'meta' => ['channel' => 'consumer_api'],
        ]);

        Http::fake([
            'https://mevon.test/*' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $this->postJson('/api/v1/webhook/mevonpay', [
            'event' => 'card.created.success',
            'data' => [
                'reference' => 'VCARD-NOTIFY-REF',
                'card_id' => 'MEVON-CARD-NOTIFY',
                'balance' => 5,
            ],
        ])->assertOk();

        Mail::assertSent(VirtualCardReadyMail::class, function (VirtualCardReadyMail $mail) {
            return $mail->hasTo('carduser@example.com');
        });

        $this->assertNotNull(data_get($row->fresh()->last_operation_payload, 'created_notified_at'));
    }

    public function test_user_can_update_card_notification_preferences(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348148790554',
            'balance' => 10000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'kyc_email' => 'prefs@example.com',
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        Sanctum::actingAs($account);

        $this->patchJson('/api/v1/consumer/wallet/card-notifications', [
            'notify_card_transaction_email' => false,
            'notify_card_created_whatsapp' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.notify_card_transaction_email', false)
            ->assertJsonPath('data.notify_card_created_whatsapp', false);

        $fresh = $wallet->fresh();
        $this->assertFalse($fresh->notify_card_transaction_email);
        $this->assertFalse($fresh->notify_card_created_whatsapp);
        $this->assertTrue($fresh->notify_card_created_email);
    }

    public function test_card_transactions_notify_once_per_mevon_row(): void
    {
        Mail::fake();

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348148790554',
            'balance' => 30000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'kyc_email' => 'txn@example.com',
            'notify_card_transaction_email' => true,
            'notify_card_transaction_whatsapp' => true,
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_ACTIVE,
            'fee_usd' => 5,
            'fee_ngn' => 8000,
            'card_external_id' => 'VCARD-TEST-001',
            'card_name' => 'Test User',
        ]);

        Http::fake([
            'https://mevon.test/V1/card_transactions' => Http::response([
                'success' => true,
                'data' => [[
                    'code' => 'notify-txn-001',
                    'description' => 'Google CLOUD',
                    'status' => 'success',
                    'amount' => 10.00,
                    'currency' => 'USD',
                    'createdOn' => '2026-06-01T15:02:13.2763678',
                    'category' => 'withdraw card',
                ]],
            ], 200),
        ]);

        Sanctum::actingAs($account);

        $this->getJson('/api/v1/consumer/cards/transactions')->assertOk();
        $this->getJson('/api/v1/consumer/cards/transactions')->assertOk();

        Mail::assertSent(VirtualCardTransactionMail::class, 1);
    }
}
