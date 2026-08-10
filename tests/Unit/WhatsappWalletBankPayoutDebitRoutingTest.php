<?php

namespace Tests\Unit;

use App\Models\MevonPayLedgerEntry;
use App\Models\WhatsappWallet;
use App\Services\MavonPayTransferService;
use App\Services\MevonPay\MevonPayPayoutService;
use App\Services\WhatsappWalletBankPayoutService;
use Mockery;
use Tests\TestCase;

class WhatsappWalletBankPayoutDebitRoutingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_explicit_business_debit_uses_payout_even_without_personal_va(): void
    {
        $payout = Mockery::mock(MevonPayPayoutService::class);
        $payout->shouldReceive('isConfigured')->andReturn(true);
        $payout->shouldReceive('createPayout')
            ->once()
            ->with(Mockery::on(function (array $args) {
                return ($args['debitAccountNumber'] ?? null) === '8888777766'
                    && ($args['debitAccountName'] ?? null) === 'Acme Biz';
            }))
            ->andReturn([
                'bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
                'response_code' => '00',
                'response_message' => 'OK',
                'reference' => 'REF-1',
                'raw' => [],
            ]);

        $mavon = Mockery::mock(MavonPayTransferService::class);
        $mavon->shouldReceive('createTransfer')->never();

        $this->app->instance(MevonPayPayoutService::class, $payout);
        $this->app->instance(MavonPayTransferService::class, $mavon);

        $wallet = new WhatsappWallet([
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'mevon_virtual_account_number' => null,
        ]);

        $result = app(WhatsappWalletBankPayoutService::class)->sendTransfer(
            500,
            '058',
            'GTBank',
            '0123456789',
            'Beneficiary',
            'REF-1',
            'Checkout App',
            $wallet,
            null,
            'Acme Biz',
            '8888777766',
        );

        $this->assertSame(MevonPayLedgerEntry::PAYOUT_API_PAYOUT, $result['payout_api'] ?? null);
    }

    public function test_without_debit_va_uses_platform_createtransfer(): void
    {
        $payout = Mockery::mock(MevonPayPayoutService::class);
        $payout->shouldReceive('isConfigured')->andReturn(true);
        $payout->shouldReceive('createPayout')->never();

        $mavon = Mockery::mock(MavonPayTransferService::class);
        $mavon->shouldReceive('createTransfer')
            ->once()
            ->andReturn([
                'bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
                'response_code' => '00',
                'response_message' => 'OK',
                'reference' => 'REF-2',
                'raw' => [],
            ]);

        $this->app->instance(MevonPayPayoutService::class, $payout);
        $this->app->instance(MavonPayTransferService::class, $mavon);

        $wallet = new WhatsappWallet([
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'mevon_virtual_account_number' => null,
        ]);

        $result = app(WhatsappWalletBankPayoutService::class)->sendTransfer(
            500,
            '058',
            'GTBank',
            '0123456789',
            'Beneficiary',
            'REF-2',
            'Checkout App',
            $wallet,
        );

        $this->assertSame(MevonPayLedgerEntry::PAYOUT_API_CREATETRANSFER, $result['payout_api'] ?? null);
    }
}
