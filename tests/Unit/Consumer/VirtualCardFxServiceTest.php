<?php

namespace Tests\Unit\Consumer;

use App\Models\Setting;
use App\Models\WhatsappCrossBorderFxRate;
use App\Services\Consumer\VirtualCardFxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualCardFxServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sell_and_buy_rates_from_mid_and_ngn_profit(): void
    {
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_sell_profit_ngn', 48, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_buy_profit_ngn', 32, 'float', 'virtual_card', 'test');

        $fx = app(VirtualCardFxService::class);

        $this->assertSame(1648.0, $fx->sellRate());
        $this->assertSame(1568.0, $fx->buyRate());

        $topup = $fx->quoteTopupNgn(10.0);
        $this->assertSame(16480.0, $topup['amount_ngn']);
        $this->assertSame('sell', $topup['fx_side']);

        $withdraw = $fx->quoteWithdrawNgn(10.0);
        $this->assertSame(15680.0, $withdraw['amount_ngn']);
        $this->assertSame('buy', $withdraw['fx_side']);
    }

    public function test_legacy_percent_maps_to_ngn_profit_when_profit_not_set(): void
    {
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_sell_markup_percent', 3, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_buy_markup_percent', 2, 'float', 'virtual_card', 'test');

        $fx = app(VirtualCardFxService::class);

        $this->assertSame(48.0, $fx->sellProfitNgnPerUsd());
        $this->assertSame(32.0, $fx->buyProfitNgnPerUsd());
        $this->assertSame(1648.0, $fx->sellRate());
        $this->assertSame(1568.0, $fx->buyRate());
    }

    public function test_explicit_rate_overrides_beat_mid_profit(): void
    {
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_sell_rate', 1700, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_buy_rate', 1500, 'float', 'virtual_card', 'test');

        $fx = app(VirtualCardFxService::class);

        $this->assertSame(1700.0, $fx->sellRate());
        $this->assertSame(1500.0, $fx->buyRate());
    }

    public function test_mid_falls_back_to_fx_table(): void
    {
        WhatsappCrossBorderFxRate::query()->create([
            'from_currency' => 'USD',
            'to_currency' => 'NGN',
            'rate' => 1550,
        ]);

        $fx = app(VirtualCardFxService::class);

        $this->assertSame(1550.0, $fx->midUsdNgnRate());
    }
}
