<?php

namespace Tests\Unit\Consumer;

use App\Models\Setting;
use App\Services\Consumer\ConsumerVirtualCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualCardRequestFeeBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_breakdown_is_two_fifty_plus_five(): void
    {
        Setting::set('virtual_card_fx_mid_auto_sync', 0, 'boolean', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_mid_usd_ngn', 1370, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_sell_profit_ngn', 15, 'float', 'virtual_card', 'test');

        $cards = app(ConsumerVirtualCardService::class);

        $this->assertSame(2.5, $cards->creationFeeUsd());
        $this->assertSame(5.0, $cards->initialLoadUsd());
        $this->assertSame(7.5, $cards->requestFeeUsd());
        $this->assertSame(5.0, $cards->mevonInitialLoadUsd());
        $this->assertSame(7.5, $cards->mevonTotalCostUsd());

        $breakdown = $cards->requestFeeBreakdown();
        $this->assertSame(2.5, $breakdown['creation_fee_usd']);
        $this->assertSame(5.0, $breakdown['initial_load_usd']);
        $this->assertSame(7.5, $breakdown['total_usd']);
        $this->assertSame(10387.5, $breakdown['total_ngn']);
    }
}
