<?php

namespace Tests\Unit\Admin;

use App\Models\Admin;
use App\Models\VirtualCardRequest;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Admin\AdminVirtualCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminVirtualCardServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createWallet(): WhatsappWallet
    {
        return WhatsappWallet::query()->create([
            'phone_e164' => '2348012345678',
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'balance' => 50000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'kyc_fname' => 'Test',
            'kyc_lname' => 'User',
            'kyc_email' => 'test@example.com',
        ]);
    }

    private function createCard(WhatsappWallet $wallet, string $status = VirtualCardRequest::STATUS_SUBMITTED): VirtualCardRequest
    {
        return VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => $status,
            'fee_usd' => 5,
            'fee_ngn' => 8000,
            'fx_rate_used' => 1600,
            'external_reference' => 'VCARD-TESTREF001',
            'card_name' => 'Test Card',
            'home_number' => '12',
            'home_address' => 'Test Street',
            'request_payload' => [
                'amount' => 5,
                'firstName' => 'Test',
                'lastName' => 'User',
                'email' => 'test@example.com',
                'phoneNumber' => '08012345678',
                'dob' => '1990-01-01',
                'homeNumber' => '12',
                'homeAddress' => 'Test Street',
                'cardName' => 'Test Card',
            ],
        ]);
    }

    private function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Ops Admin',
            'email' => 'ops-'.uniqid().'@test.local',
            'password' => bcrypt('secret'),
            'role' => 'admin',
        ]);
    }

    public function test_mark_active_from_submitted(): void
    {
        $wallet = $this->createWallet();
        $card = $this->createCard($wallet);
        $admin = $this->createAdmin();

        $svc = app(AdminVirtualCardService::class);
        $result = $svc->markActive($card, $admin);

        $this->assertTrue($result['ok']);
        $card->refresh();
        $this->assertSame(VirtualCardRequest::STATUS_ACTIVE, $card->status);
        $this->assertNotNull($card->activated_at);
        $this->assertSame($admin->id, $card->handled_by_admin_id);
    }

    public function test_mark_failed_requires_reason(): void
    {
        $wallet = $this->createWallet();
        $card = $this->createCard($wallet, VirtualCardRequest::STATUS_PENDING);
        $admin = $this->createAdmin();

        $svc = app(AdminVirtualCardService::class);
        $result = $svc->markFailed($card, $admin, '');

        $this->assertFalse($result['ok']);
    }

    public function test_refund_fee_is_idempotent(): void
    {
        $wallet = $this->createWallet();
        $card = $this->createCard($wallet, VirtualCardRequest::STATUS_FAILED);
        $admin = $this->createAdmin();

        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'sender_name' => 'Test User',
            'type' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
            'amount' => 8000,
            'balance_after' => 42000,
            'external_reference' => $card->external_reference,
            'meta' => ['channel' => 'consumer_api'],
        ]);

        $wallet->update(['balance' => 42000]);

        $svc = app(AdminVirtualCardService::class);
        $first = $svc->refundFee($card, $admin);
        $this->assertTrue($first['ok']);

        $wallet->refresh();
        $this->assertSame(50000.0, (float) $wallet->balance);

        $second = $svc->refundFee($card->fresh(), $admin);
        $this->assertTrue($second['ok']);
        $this->assertTrue($second['already_refunded'] ?? false);

        $wallet->refresh();
        $this->assertSame(50000.0, (float) $wallet->balance);
    }

    public function test_retry_does_not_create_duplicate_fee_transaction(): void
    {
        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'test-secret',
        ]);

        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'data' => [
                    'bal' => '500000',
                    'usd_balance' => '20.00',
                ],
            ], 200),
            'https://mevon.test/V1/card_request' => Http::response([
                'status' => 'success',
                'data' => ['card_id' => 'CARD-99'],
            ], 200),
        ]);

        $wallet = $this->createWallet();
        $card = $this->createCard($wallet, VirtualCardRequest::STATUS_FAILED);

        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'sender_name' => 'Test User',
            'type' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
            'amount' => 8000,
            'balance_after' => 42000,
            'external_reference' => $card->external_reference,
        ]);

        $beforeCount = WhatsappWalletTransaction::query()->count();

        $svc = app(AdminVirtualCardService::class);
        $result = $svc->retryProvider($card);

        $this->assertTrue($result['ok']);
        $this->assertSame($beforeCount, WhatsappWalletTransaction::query()->count());

        $card->refresh();
        $this->assertSame(VirtualCardRequest::STATUS_SUBMITTED, $card->status);
        $this->assertSame('CARD-99', $card->card_external_id);
    }
}
