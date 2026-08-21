<?php

namespace Tests\Unit\MevonPay;

use App\Models\Admin;
use App\Models\MevonPayDiscrepancyAlert;
use App\Models\MevonPayLedgerEntry;
use App\Models\Setting;
use App\Services\MavonPayTransferService;
use App\Services\MevonPay\MevonPayBalanceMonitorService;
use App\Services\MevonPay\MevonPayLedgerRecorder;
use App\Services\MevonPay\MevonPayReconBaselineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MevonPayBalanceMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'secret_key',
        ]);
    }

    public function test_baseline_init_stores_settings_and_ignores_pre_baseline_entries(): void
    {
        $admin = $this->makeAdmin();

        $recorder = app(MevonPayLedgerRecorder::class);
        $recorder->recordInbound(
            MevonPayLedgerEntry::FLOW_WHATSAPP_TOPUP,
            5000,
            'pre-baseline-ref',
            '1234567890',
            null,
            [],
            now()->subHour(),
        );

        $this->mockBalanceSnapshot(100_000.00, 100_000.00);

        $baseline = app(MevonPayReconBaselineService::class);
        $result = $baseline->initialize($admin);

        $this->assertSame(100_000.00, $result['opening_balance']);
        $this->assertTrue($baseline->isActive());
        $this->assertSame(100_000.00, Setting::get(MevonPayReconBaselineService::KEY_OPENING_BALANCE));
        $this->assertSame($admin->id, Setting::get(MevonPayReconBaselineService::KEY_STARTED_BY_ADMIN_ID));

        $recorder->recordInbound(
            MevonPayLedgerEntry::FLOW_WHATSAPP_TOPUP,
            2000,
            'post-baseline-ref',
            '1234567891',
            null,
            [],
            now(),
        );

        $monitor = app(MevonPayBalanceMonitorService::class);
        $summary = $monitor->summary();

        $this->assertSame(1, $summary['entry_count']);
        $this->assertSame(1970.0, $summary['net_mevon_impact']);
        $this->assertSame(round(100_000.00 + 1970.0, 2), $summary['expected_balance']);
    }

    public function test_running_balance_on_three_sample_entries(): void
    {
        $admin = $this->makeAdmin();
        $this->mockBalanceSnapshot(10_000.00);
        app(MevonPayReconBaselineService::class)->initialize($admin);

        $baselineAt = app(MevonPayReconBaselineService::class)->baselineAt();
        $this->assertNotNull($baselineAt);

        $recorder = app(MevonPayLedgerRecorder::class);
        $t1 = $baselineAt->copy()->addMinute();
        $t2 = $baselineAt->copy()->addMinutes(2);
        $t3 = $baselineAt->copy()->addMinutes(3);

        $recorder->recordInbound(MevonPayLedgerEntry::FLOW_WHATSAPP_TOPUP, 1000, 'run-1', '111', null, [], $t1);
        $recorder->recordInbound(MevonPayLedgerEntry::FLOW_WHATSAPP_TOPUP, 2000, 'run-2', '222', null, [], $t2);
        $recorder->recordOutbound(
            MevonPayLedgerEntry::FLOW_WHATSAPP_BANK_TRANSFER,
            500,
            'run-out-1',
            MevonPayLedgerEntry::PAYOUT_API_CREATETRANSFER,
            MavonPayTransferService::BUCKET_SUCCESSFUL,
            null,
            null,
            [],
            $t3,
        );

        $monitor = app(MevonPayBalanceMonitorService::class);
        $ledger = $monitor->ledgerWithRunningBalance(50);
        $rows = $ledger->getCollection()->keyBy('id');

        $inboundNet1 = 970.0; // 1000 - 30
        $inboundNet2 = 1970.0; // 2000 - 30
        $outboundNet = -510.0;

        $byRef = $rows->keyBy(fn ($row) => $row->external_reference ?: $row->payout_reference);

        $this->assertSame(round(10_000 + $inboundNet1, 2), (float) $byRef['run-1']->running_expected_balance);
        $this->assertSame(round(10_000 + $inboundNet1 + $inboundNet2, 2), (float) $byRef['run-2']->running_expected_balance);
        $this->assertSame(round(10_000 + $inboundNet1 + $inboundNet2 + $outboundNet, 2), (float) $byRef['run-out-1']->running_expected_balance);
    }

    public function test_alert_created_when_variance_exceeds_tolerance(): void
    {
        $admin = $this->makeAdmin();

        Http::fake([
            'https://mevon.test/V1/balance' => Http::sequence()
                ->push([
                    'success' => true,
                    'message' => 'OK',
                    'data' => ['bal' => 10_000.00, 'ledger_bal' => 10_000.00],
                ], 200)
                ->push([
                    'success' => true,
                    'message' => 'OK',
                    'data' => ['bal' => 9_000.00, 'ledger_bal' => 9_000.00],
                ], 200),
        ]);

        app(MevonPayReconBaselineService::class)->initialize($admin);

        $monitor = app(MevonPayBalanceMonitorService::class);
        $result = $monitor->checkNow(MevonPayDiscrepancyAlert::TRIGGER_MANUAL);

        $this->assertTrue($result['alert_created']);
        $this->assertFalse($result['within_tolerance']);
        $this->assertDatabaseCount('mevon_pay_discrepancy_alerts', 1);
        $this->assertDatabaseHas('mevon_pay_discrepancy_alerts', [
            'expected_balance' => 10000.00,
            'live_balance' => 9000.00,
            'variance_amount' => -1000.00,
            'trigger' => MevonPayDiscrepancyAlert::TRIGGER_MANUAL,
        ]);
    }

    public function test_alert_not_created_when_within_tolerance(): void
    {
        $admin = $this->makeAdmin();
        $this->mockBalanceSnapshot(10_000.00);
        app(MevonPayReconBaselineService::class)->initialize($admin);

        app(MevonPayLedgerRecorder::class)->recordInbound(
            MevonPayLedgerEntry::FLOW_WHATSAPP_TOPUP,
            1000,
            'tol-ref',
            '999',
            null,
            [],
            now(),
        );

        $expected = round(10_000 + 970, 2);
        $this->mockBalanceSnapshot($expected);

        $result = app(MevonPayBalanceMonitorService::class)->checkNow(MevonPayDiscrepancyAlert::TRIGGER_SCHEDULED);

        $this->assertFalse($result['alert_created']);
        $this->assertTrue($result['within_tolerance']);
        $this->assertDatabaseCount('mevon_pay_discrepancy_alerts', 0);
    }

    private function mockBalanceSnapshot(float $nairaBalance, ?float $nairaLedger = null): void
    {
        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'success' => true,
                'message' => 'OK',
                'data' => [
                    'bal' => $nairaBalance,
                    'ledger_bal' => $nairaLedger ?? $nairaBalance,
                ],
            ], 200),
        ]);
    }

    private function makeAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'role' => Admin::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }
}
