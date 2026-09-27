<?php

namespace Tests\Unit\Consumer;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Models\WhatsappWalletTransferBeneficiary;
use App\Services\Checkface\CheckfaceClient;
use App\Services\Consumer\WalletFaceCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WalletFaceCheckServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeWallet(array $overrides = []): WhatsappWallet
    {
        return WhatsappWallet::query()->create(array_merge([
            'phone_e164' => '2348012345678',
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'balance' => 100000,
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
        ], $overrides));
    }

    private function service(bool $enabled = true): WalletFaceCheckService
    {
        config([
            'checkface.enabled' => $enabled,
            'checkface.base_url' => 'http://127.0.0.1:8025',
            'checkface.api_token' => 'test-token',
            'checkface.amount_threshold_ngn' => 30000,
            'checkface.frequent_min_transfers' => 3,
            'checkface.frequent_lookback_days' => 365,
            'checkface.challenge_ttl_minutes' => 5,
        ]);

        $client = $this->createMock(CheckfaceClient::class);
        $client->method('isConfigured')->willReturn($enabled);

        return new WalletFaceCheckService($client);
    }

    public function test_below_threshold_does_not_require_face(): void
    {
        $wallet = $this->makeWallet();
        $svc = $this->service();

        $this->assertFalse($svc->requiresFaceCheck($wallet, 29999.99, 'bank', '0123456789', '044'));
    }

    public function test_saved_beneficiary_skips_face_gate(): void
    {
        $wallet = $this->makeWallet();
        WhatsappWalletTransferBeneficiary::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'kind' => 'bank',
            'destination_key' => WhatsappWalletTransferBeneficiary::bankDestinationKey('044', '0123456789'),
            'account_number' => '0123456789',
            'bank_code' => '044',
            'display_name' => 'Ada',
        ]);

        $svc = $this->service();
        $this->assertFalse($svc->requiresFaceCheck($wallet, 50000, 'bank', '0123456789', '044'));
        $this->assertNull($svc->rejectIfRequired($wallet, 50000, 'bank', null, '0123456789', '044'));
    }

    public function test_frequent_bank_recipient_skips_face_gate(): void
    {
        $wallet = $this->makeWallet();
        for ($i = 0; $i < 3; $i++) {
            WhatsappWalletTransaction::query()->create([
                'whatsapp_wallet_id' => $wallet->id,
                'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
                'amount' => 1000,
                'balance_after' => 0,
                'counterparty_account_number' => '0123456789',
                'counterparty_bank_code' => '044',
                'counterparty_account_name' => 'Ada',
                'meta' => [],
            ]);
        }

        $svc = $this->service();
        $this->assertFalse($svc->requiresFaceCheck($wallet, 50000, 'bank', '0123456789', '044'));
    }

    public function test_unfamiliar_recipient_requires_enrollment_or_token(): void
    {
        $wallet = $this->makeWallet(['face_enrolled_at' => null]);
        $client = $this->createMock(CheckfaceClient::class);
        $client->method('isConfigured')->willReturn(true);
        $client->method('learningStatus')->willReturn([
            'ok' => true,
            'status' => 200,
            'data' => ['profile_stored' => false, 'gallery_size' => 0],
            'message' => 'OK',
        ]);
        $svc = new WalletFaceCheckService($client);
        config([
            'checkface.enabled' => true,
            'checkface.base_url' => 'http://127.0.0.1:8025',
            'checkface.api_token' => 'test-token',
            'checkface.amount_threshold_ngn' => 30000,
            'checkface.frequent_min_transfers' => 3,
        ]);

        $response = $svc->rejectIfRequired($wallet, 50000, 'bank', null, '0123456789', '044');
        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('face_enrollment_required', $response->getData(true)['error_code'] ?? null);
    }

    public function test_valid_face_token_clears_gate(): void
    {
        $wallet = $this->makeWallet(['face_enrolled_at' => now()]);
        $client = $this->createMock(CheckfaceClient::class);
        $client->method('isConfigured')->willReturn(true);
        $client->method('learningStatus')->willReturn([
            'ok' => true,
            'status' => 200,
            'data' => ['profile_stored' => true, 'gallery_size' => 2],
            'message' => 'OK',
        ]);
        $svc = new WalletFaceCheckService($client);
        config([
            'checkface.enabled' => true,
            'checkface.base_url' => 'http://127.0.0.1:8025',
            'checkface.api_token' => 'test-token',
            'checkface.amount_threshold_ngn' => 30000,
            'checkface.frequent_min_transfers' => 3,
        ]);

        Cache::put('wallet_face_challenge:ftok_test', [
            'wallet_id' => (int) $wallet->id,
            'kind' => 'bank',
            'destination_key' => WhatsappWalletTransferBeneficiary::bankDestinationKey('044', '0123456789'),
            'amount' => 50000.0,
        ], now()->addMinutes(5));

        $this->assertNull($svc->rejectIfRequired($wallet, 50000, 'bank', 'ftok_test', '0123456789', '044'));
    }
}
