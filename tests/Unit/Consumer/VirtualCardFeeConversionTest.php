<?php

namespace Tests\Unit\Consumer;

use App\Models\Setting;
use App\Services\Consumer\VirtualCardFxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualCardFeeConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_usd_to_ngn_conversion_for_card_fee_uses_sell_rate(): void
    {
        Setting::set('virtual_card_fx_mid_usd_ngn', 1600, 'float', 'vtu', 'test');
        Setting::set('virtual_card_fx_sell_markup_percent', 0, 'float', 'vtu', 'test');

        $fx = app(VirtualCardFxService::class);
        $ngn = $fx->quoteRequestFeeNgn(5.0);

        $this->assertSame(8000.0, $ngn);
    }
}
