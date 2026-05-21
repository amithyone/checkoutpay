<?php

namespace Tests\Unit\Consumer;

use App\Models\WhatsappCrossBorderFxRate;
use App\Services\Whatsapp\WhatsappCrossBorderP2pFxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualCardFeeConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_usd_to_ngn_conversion_for_card_fee(): void
    {
        WhatsappCrossBorderFxRate::query()->create([
            'from_currency' => 'USD',
            'to_currency' => 'NGN',
            'rate' => 1600,
        ]);

        $fx = app(WhatsappCrossBorderP2pFxService::class);
        $ngn = $fx->convertCurrency('USD', 'NGN', 5.0);

        $this->assertSame(8000.0, $ngn);
    }
}
