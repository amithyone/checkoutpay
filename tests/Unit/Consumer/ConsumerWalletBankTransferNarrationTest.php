<?php

namespace Tests\Unit\Consumer;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletTransferService;
use App\Services\MavonPayTransferService;
use App\Services\Payout\BankPayoutNarration;
use App\Services\WhatsappWalletBankPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConsumerWalletBankTransferNarrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_bank_transfer_passes_checkout_app_narration_when_remark_empty(): void
    {
        $capturedNarration = null;
        $this->mockPayout($capturedNarration);

        $wallet = $this->makeNigeriaWallet();
        $service = app(ConsumerWalletTransferService::class);

        $service->bankTransfer(
            $wallet,
            1000,
            '0123456789',
            '058',
            'GTBank',
            'Test Beneficiary',
            null,
        );

        $this->assertSame(BankPayoutNarration::CONSUMER_APP_DEFAULT, $capturedNarration);

        $txn = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($txn);
        $this->assertSame(BankPayoutNarration::CONSUMER_APP_DEFAULT, $txn->meta['narration'] ?? null);
    }

    public function test_bank_transfer_passes_custom_remark_as_narration(): void
    {
        $capturedNarration = null;
        $this->mockPayout($capturedNarration);

        $wallet = $this->makeNigeriaWallet();
        $service = app(ConsumerWalletTransferService::class);

        $service->bankTransfer(
            $wallet,
            1000,
            '0123456789',
            '058',
            'GTBank',
            'Test Beneficiary',
            '  Rent for May  ',
        );

        $this->assertSame('Rent for May', $capturedNarration);
    }

    private function mockPayout(?string &$capturedNarration): void
    {
        $mock = Mockery::mock(WhatsappWalletBankPayoutService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('isNameEnquiryAvailable')->andReturn(false);
        $mock->shouldReceive('makeWalletPayoutReference')->andReturn('REF-TEST-001');
        $mock->shouldReceive('sendTransfer')
            ->once()
            ->withArgs(function (...$args) use (&$capturedNarration) {
                $capturedNarration = $args[6] ?? null;

                return true;
            })
            ->andReturn([
                'bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
                'reference' => 'REF-TEST-001',
                'response_code' => '00',
                'response_message' => 'OK',
            ]);

        $this->app->instance(WhatsappWalletBankPayoutService::class, $mock);
    }

    private function makeNigeriaWallet(): WhatsappWallet
    {
        return WhatsappWallet::query()->create([
            'phone_e164' => '2348012345678',
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'balance' => 50000,
            'pin_hash' => bcrypt('1234'),
            'tier' => 1,
            'daily_transfer_total' => 0,
            'daily_transfer_for_date' => now()->toDateString(),
        ]);
    }
}
