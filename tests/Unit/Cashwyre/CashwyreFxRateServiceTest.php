<?php

namespace Tests\Unit\Cashwyre;

use App\Services\Cashwyre\CashwyreFxRateService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CashwyreFxRateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cashwyre.base_url' => 'https://cashwyre.test/api/v1.0',
            'cashwyre.app_id' => 'app-id',
            'cashwyre.business_code' => 'biz-code',
            'cashwyre.secret_key' => 'secret',
            'cashwyre.fx_rate_cache_seconds' => 600,
            'cashwyre.paths.get_fx_rates' => '/businessRate/getFxRates',
        ]);
    }

    public function test_ngn_usd_rates_parses_cashwyre_fx_response(): void
    {
        Http::fake([
            'https://cashwyre.test/api/v1.0/businessRate/getFxRates' => Http::response([
                'success' => true,
                'message' => 'Request Successful',
                'data' => [
                    [
                        'currency' => 'KES',
                        'buyRate' => 129.21,
                        'sellRate' => 135.21,
                    ],
                    [
                        'currency' => 'NGN',
                        'buyRate' => 1420,
                        'sellRate' => 1475,
                        'buyRateInfo' => '$1 = NGN 1420.00',
                        'sellRateInfo' => '$1 = NGN 1475.00',
                    ],
                ],
            ], 200),
        ]);

        $result = app(CashwyreFxRateService::class)->ngnUsdRatesFresh();

        $this->assertTrue($result['ok']);
        $this->assertSame(1420.0, $result['buy_rate']);
        $this->assertSame(1475.0, $result['sell_rate']);
        $this->assertSame(1447.5, $result['mid']);
        $this->assertSame('$1 = NGN 1420.00', $result['buy_rate_info']);
    }

    public function test_ngn_usd_rates_returns_error_when_ngn_missing(): void
    {
        Http::fake([
            'https://cashwyre.test/api/v1.0/businessRate/getFxRates' => Http::response([
                'success' => true,
                'message' => 'Request Successful',
                'data' => [
                    ['currency' => 'KES', 'buyRate' => 129.21, 'sellRate' => 135.21],
                ],
            ], 200),
        ]);

        $result = app(CashwyreFxRateService::class)->ngnUsdRatesFresh();

        $this->assertFalse($result['ok']);
        $this->assertSame('Cashwyre did not return NGN FX rates.', $result['message']);
    }
}
