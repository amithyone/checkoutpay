<?php

namespace Tests\Unit\Support;

use App\Models\MevonPayFxRateSnapshot;
use App\Models\Setting;
use App\Support\MarketingVirtualCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingVirtualCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('virtual_card_enabled', true, 'boolean', 'virtual_card', 'test');
    }

    public function test_app_rates_use_published_settings_not_rate_tracker_snapshot(): void
    {
        Setting::set('virtual_card_fx_published_mid', 1378.08, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_published_sell_rate', 1388.08, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_published_buy_rate', 1368.08, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_published_at', now()->toIso8601String(), 'string', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_published_source', 'mevon_live', 'string', 'virtual_card', 'test');

        MevonPayFxRateSnapshot::query()->create([
            'recorded_at' => now(),
            'mevon_mid' => 1378.08,
            'published_mid' => 1378.08,
            'sell_rate' => 1393.08,
            'buy_rate' => 1348.08,
            'source' => 'mevon_live',
        ]);

        $rates = MarketingVirtualCard::appRates(fetchFresh: false);

        $this->assertTrue($rates['ok']);
        $this->assertSame(1388.08, $rates['sell_rate']);
        $this->assertSame(1368.08, $rates['buy_rate']);
        $this->assertSame(1378.08, $rates['mid']);
    }
}
