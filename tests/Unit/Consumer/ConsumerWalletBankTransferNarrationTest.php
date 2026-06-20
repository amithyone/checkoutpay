<?php

namespace Tests\Unit\Consumer;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletTransactionScope;
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

    public function test_business_bank_transfer_uses_business_name_as_sender_and_checkout_app_narration(): void
    {
        $capturedNarration = null;
        $capturedDebitName = null;
        $capturedDebitNumber = null;
        $this->mockPayout($capturedNarration, $capturedDebitName, $capturedDebitNumber);

        config([
            'services.mevonpay.debit_account_name' => 'Checkout',
            'services.mevonpay.debit_account_number' => '9000000001',
        ]);

        $business = \App\Models\Business::query()->create([
            'name' => 'Acme Ventures Ltd',
            'email' => 'acme@example.com',
            'password' => bcrypt('secret'),
            'business_id' => 'ACME99',
            'phone' => '08012345678',
            'balance' => 100000,
        ]);

        $wallet = $this->makeNigeriaWallet([
            'linked_business_id' => $business->id,
            'business_balance' => 100000,
            'sender_name' => 'John Personal',
            'mevon_virtual_account_number' => '1111222233',
            'mevon_account_name' => 'John Personal',
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $service = app(ConsumerWalletTransferService::class);
        $service->bankTransfer(
            $wallet,
            1000,
            '0123456789',
            '058',
            'GTBank',
            'Test Beneficiary',
            null,
            ConsumerWalletTransactionScope::SCOPE_BUSINESS,
        );

        $this->assertSame(BankPayoutNarration::CONSUMER_APP_DEFAULT, $capturedNarration);
        $this->assertSame('Acme Ventures Ltd', $capturedDebitName);
        $this->assertSame('9000000001', $capturedDebitNumber);
        $this->assertNotSame('1111222233', $capturedDebitNumber);

        $txn = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($txn);
        $this->assertSame('Acme Ventures Ltd', $txn->sender_name);
        $this->assertSame(BankPayoutNarration::CONSUMER_APP_DEFAULT, $txn->meta['narration'] ?? null);
        $this->assertSame(ConsumerWalletTransactionScope::SCOPE_BUSINESS, $txn->ledger_scope);
    }

    public function test_business_bank_transfer_uses_merchant_rubies_va_as_debit_account(): void
    {
        $capturedDebitName = null;
        $capturedDebitNumber = null;
        $this->mockPayout(null, $capturedDebitName, $capturedDebitNumber);

        $business = \App\Models\Business::query()->create([
            'name' => 'Acme Ventures Ltd',
            'email' => 'acme-rubies@example.com',
            'password' => bcrypt('secret'),
            'business_id' => 'ACME-RUB',
            'phone' => '08012345679',
            'balance' => 100000,
            'rubies_business_account_number' => '8888777766',
            'rubies_business_account_name' => 'Acme Ventures Ltd',
        ]);

        $wallet = $this->makeNigeriaWallet([
            'linked_business_id' => $business->id,
            'business_balance' => 100000,
            'sender_name' => 'John Personal',
            'mevon_virtual_account_number' => '1111222233',
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        app(ConsumerWalletTransferService::class)->bankTransfer(
            $wallet,
            1000,
            '0123456789',
            '058',
            'GTBank',
            'Test Beneficiary',
            null,
            ConsumerWalletTransactionScope::SCOPE_BUSINESS,
        );

        $this->assertSame('Acme Ventures Ltd', $capturedDebitName);
        $this->assertSame('8888777766', $capturedDebitNumber);
        $this->assertNotSame('1111222233', $capturedDebitNumber);
    }

    public function test_personal_bank_transfer_uses_wallet_va_as_debit_account(): void
    {
        $capturedDebitName = null;
        $capturedDebitNumber = null;
        $this->mockPayout(null, $capturedDebitName, $capturedDebitNumber);

        $wallet = $this->makeNigeriaWallet([
            'sender_name' => 'Jane Personal',
            'mevon_virtual_account_number' => '5555666677',
            'mevon_account_name' => 'Jane Personal',
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        app(ConsumerWalletTransferService::class)->bankTransfer(
            $wallet,
            1000,
            '0123456789',
            '058',
            'GTBank',
            'Test Beneficiary',
            null,
        );

        $this->assertSame('Jane Personal', $capturedDebitName);
        $this->assertSame('5555666677', $capturedDebitNumber);
    }

    private function mockPayout(?string &$capturedNarration, ?string &$capturedDebitName = null, ?string &$capturedDebitNumber = null): void
    {
        $mock = Mockery::mock(WhatsappWalletBankPayoutService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('isNameEnquiryAvailable')->andReturn(false);
        $mock->shouldReceive('makeWalletPayoutReference')->andReturn('REF-TEST-001');
        $mock->shouldReceive('sendTransfer')
            ->once()
            ->withArgs(function (...$args) use (&$capturedNarration, &$capturedDebitName, &$capturedDebitNumber) {
                $capturedNarration = $args[6] ?? null;
                $capturedDebitName = $args[9] ?? null;
                $capturedDebitNumber = $args[10] ?? null;

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

    private function makeNigeriaWallet(array $overrides = []): WhatsappWallet
    {
        return WhatsappWallet::query()->create(array_merge([
            'phone_e164' => '2348012345678',
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'balance' => 50000,
            'pin_hash' => bcrypt('1234'),
            'tier' => 1,
            'daily_transfer_total' => 0,
            'daily_transfer_for_date' => now()->toDateString(),
        ], $overrides));
    }
}
