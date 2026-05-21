<?php

namespace Tests\Unit\MevonPay;

use App\Services\MevonPay\MevonPayBalanceSnapshotService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MevonPayBalanceSnapshotServiceTest extends TestCase
{
    public function test_parses_standard_mevon_balance_payload(): void
    {
        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'test-secret',
            'mevonpay_vtu.paths.balance' => '/V1/balance',
        ]);

        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'data' => [
                    'bal' => '100',
                    'usd_balance' => '0.00',
                    'ledger_bal' => '255000.00',
                    'usd_ledger_bal' => '2.00',
                ],
            ], 200),
        ]);

        $snapshot = app(MevonPayBalanceSnapshotService::class)->forDashboard();

        $this->assertTrue($snapshot['configured']);
        $this->assertTrue($snapshot['ok']);
        $this->assertSame(100.0, $snapshot['naira_balance']);
        $this->assertSame(0.0, $snapshot['usd_balance']);
        $this->assertSame(255000.0, $snapshot['naira_ledger']);
        $this->assertSame(2.0, $snapshot['usd_ledger']);
    }

    public function test_returns_not_configured_when_keys_missing(): void
    {
        config([
            'services.mevonpay.base_url' => '',
            'services.mevonpay.secret_key' => '',
        ]);

        $snapshot = app(MevonPayBalanceSnapshotService::class)->forDashboard();

        $this->assertFalse($snapshot['configured']);
        $this->assertFalse($snapshot['ok']);
        $this->assertStringContainsString('not configured', strtolower($snapshot['message']));
    }
}
