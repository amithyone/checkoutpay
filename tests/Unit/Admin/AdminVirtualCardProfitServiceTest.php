<?php

namespace Tests\Unit\Admin;

use App\Services\Admin\AdminVirtualCardProfitService;
use Tests\TestCase;

class AdminVirtualCardProfitServiceTest extends TestCase
{
    public function test_profit_from_sell_side_meta(): void
    {
        $service = app(AdminVirtualCardProfitService::class);

        $profit = $service->profitNgnFromMeta([
            'amount_usd' => 10,
            'fx_mid_usd_ngn' => 1600,
            'sell_rate' => 1650,
        ], 'topup');

        $this->assertSame(500.0, $profit);
    }

    public function test_profit_from_buy_side_meta(): void
    {
        $service = app(AdminVirtualCardProfitService::class);

        $profit = $service->profitNgnFromMeta([
            'amount_usd' => 10,
            'fx_mid_usd_ngn' => 1600,
            'buy_rate' => 1550,
        ], 'withdraw');

        $this->assertSame(500.0, $profit);
    }

    public function test_profit_from_request_fee_meta(): void
    {
        $service = app(AdminVirtualCardProfitService::class);

        $profit = $service->profitNgnFromMeta([
            'fee_usd' => 5,
            'fx_mid_usd_ngn' => 1600,
            'sell_rate' => 1620,
        ], 'fee');

        $this->assertSame(100.0, $profit);
    }

    public function test_profit_from_setup_breakdown_meta(): void
    {
        $service = app(AdminVirtualCardProfitService::class);

        $profit = $service->profitNgnFromMeta([
            'fee_usd' => 7.5,
            'creation_fee_usd' => 2.5,
            'initial_load_usd' => 5,
            'fx_mid_usd_ngn' => 1370,
            'sell_rate' => 1385,
        ], 'fee');

        $this->assertSame(112.5, $profit);
    }
}
