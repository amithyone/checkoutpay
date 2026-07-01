<?php

namespace Tests\Unit\Consumer;

use App\Models\Business;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use App\Services\Consumer\ConsumerWalletTransferService;
use App\Services\MavonPayTransferService;
use App\Services\WhatsappWalletBankPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ConsumerLinkedBusinessBalanceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_linked_business_bank_transfer_debits_merchant_and_wallet_balances(): void
    {
        $this->mockSuccessfulPayout();

        [$business, $wallet] = $this->makeLinkedPair(100000, 100000);

        app(ConsumerWalletTransferService::class)->bankTransfer(
            $wallet,
            2500,
            '0123456789',
            '058',
            'GTBank',
            'Jane Doe',
            null,
            ConsumerWalletTransactionScope::SCOPE_BUSINESS,
        );

        $business->refresh();
        $wallet->refresh();

        $this->assertSame(97500.0, (float) $business->balance);
        $this->assertSame(97500.0, (float) $wallet->business_balance);
        $this->assertSame(97500.0, app(ConsumerBusinessWalletLedgerService::class)->resolvedBalance($wallet));
    }

    public function test_debit_locked_wallet_updates_merchant_balance_when_linked(): void
    {
        [$business, $wallet] = $this->makeLinkedPair(50000, 50000);
        $ledger = app(ConsumerBusinessWalletLedgerService::class);

        DB::transaction(function () use ($ledger, $wallet): void {
            $locked = WhatsappWallet::query()->lockForUpdate()->find($wallet->id);
            $this->assertNotNull($locked);

            $result = $ledger->debitLockedWallet($locked, 1500);
            $this->assertTrue($result['ok']);
            $this->assertSame(48500.0, $result['balance_after']);
            $locked->save();
        });

        $business->refresh();
        $wallet->refresh();

        $this->assertSame(48500.0, (float) $business->balance);
        $this->assertSame(48500.0, (float) $wallet->business_balance);
    }

    public function test_merchant_balance_change_syncs_linked_wallet_cache(): void
    {
        [$business, $wallet] = $this->makeLinkedPair(20000, 20000);

        $business->increment('balance', 5000);

        $wallet->refresh();

        $this->assertSame(25000.0, (float) $business->balance);
        $this->assertSame(25000.0, (float) $wallet->business_balance);
    }

    public function test_merchant_withdrawal_syncs_linked_wallet_cache(): void
    {
        [$business, $wallet] = $this->makeLinkedPair(30000, 30000);

        $business->decrement('balance', 7000);

        $wallet->refresh();

        $this->assertSame(23000.0, (float) $business->balance);
        $this->assertSame(23000.0, (float) $wallet->business_balance);
    }

    /**
     * @return array{0: Business, 1: WhatsappWallet}
     */
    private function makeLinkedPair(float $merchantBalance, float $walletBusinessBalance): array
    {
        $business = Business::query()->create([
            'name' => 'Sync Test Merchant',
            'email' => 'sync-merchant-'.uniqid('', true).'@example.com',
            'password' => bcrypt('secret'),
            'business_id' => 'SY'.random_int(100, 999),
            'phone' => '08012345678',
            'balance' => $merchantBalance,
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348012345678',
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'balance' => 10000,
            'business_balance' => $walletBusinessBalance,
            'linked_business_id' => $business->id,
            'pin_hash' => bcrypt('1234'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'daily_transfer_total' => 0,
            'daily_transfer_for_date' => now()->toDateString(),
        ]);

        return [$business, $wallet];
    }

    private function mockSuccessfulPayout(): void
    {
        $mock = Mockery::mock(WhatsappWalletBankPayoutService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('isNameEnquiryAvailable')->andReturn(false);
        $mock->shouldReceive('makeWalletPayoutReference')->andReturn('REF-SYNC-001');
        $mock->shouldReceive('sendTransfer')->once()->andReturn([
            'bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
            'reference' => 'REF-SYNC-001',
            'response_code' => '00',
            'response_message' => 'OK',
        ]);

        $this->app->instance(WhatsappWalletBankPayoutService::class, $mock);
    }
}
