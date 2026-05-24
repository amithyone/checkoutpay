<?php

namespace Tests\Unit\MevonPay;

use App\Models\MevonPayLedgerEntry;
use App\Services\MavonPayTransferService;
use App\Services\MevonPay\MevonPayLedgerRecorder;
use App\Services\MevonPay\MevonPayPayoutService;
use App\Services\MevonPay\MevonPayReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MevonPayLedgerAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_recorder_inbound_idempotent(): void
    {
        $recorder = app(MevonPayLedgerRecorder::class);
        $first = $recorder->recordInbound(MevonPayLedgerEntry::FLOW_WHATSAPP_TOPUP, 5000, 'ref-abc', '1234567890');
        $second = $recorder->recordInbound(MevonPayLedgerEntry::FLOW_WHATSAPP_TOPUP, 5000, 'ref-abc', '1234567890');
        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertDatabaseCount('mevon_pay_ledger_entries', 1);
    }

    public function test_payout_service_successful_response(): void
    {
        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'secret_key',
            'services.mevonpay.current_password' => 'pass',
        ]);
        Http::fake(['https://mevon.test/V1/payout' => Http::response(['responseCode' => '00', 'responseMessage' => 'OK'], 200)]);
        $result = app(MevonPayPayoutService::class)->createPayout([
            'amount' => 100,
            'bankCode' => '100004',
            'bankName' => 'Opay',
            'creditAccountName' => 'Test',
            'creditAccountNumber' => '8146234809',
            'debitAccountNumber' => '1000002144',
            'debitAccountName' => 'Wallet User',
            'reference' => 'MEV_test',
        ]);
        $this->assertSame(MavonPayTransferService::BUCKET_SUCCESSFUL, $result['bucket']);
    }
}
