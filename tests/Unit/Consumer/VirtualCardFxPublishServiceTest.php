<?php

namespace Tests\Unit\Consumer;

use App\Models\Setting;
use App\Services\Consumer\VirtualCardFxPublishService;
use App\Services\Consumer\VirtualCardFxService;
use App\Services\VirtualCard\VirtualCardProviderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VirtualCardFxPublishServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cashwyre.base_url' => 'https://cashwyre.test/api/v1.0',
            'cashwyre.app_id' => 'app-id',
            'cashwyre.business_code' => 'biz-code',
            'cashwyre.secret_key' => 'secret',
            'cashwyre.paths.get_fx_rates' => '/businessRate/getFxRates',
        ]);
    }

    public function test_publish_from_mevon_writes_settings_for_consumer(): void
    {
        Cache::flush();

        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'test-secret',
            'mevonpay_vtu.paths.exchange' => '/V1/exchange',
        ]);

        Http::fake([
            'https://mevon.test/V1/exchange' => Http::response([
                'status' => true,
                'data' => ['rate' => '1370.00'],
            ], 200),
        ]);

        Setting::set('virtual_card_fx_mid_auto_sync', 1, 'boolean', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_sell_profit_ngn', 15, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_buy_profit_ngn', 30, 'float', 'virtual_card', 'test');

        $publish = app(VirtualCardFxPublishService::class);
        $result = $publish->syncFromMevon();

        $this->assertTrue($result['ok']);
        $this->assertSame(1370.0, $result['mid']);
        $this->assertSame(1385.0, $result['sell_rate']);
        $this->assertSame(1340.0, $result['buy_rate']);

        Http::fake();

        $fx = app(VirtualCardFxService::class);
        $this->assertSame(1370.0, $fx->midUsdNgnRate());
        $this->assertSame(1385.0, $fx->sellRate());
        $this->assertSame(1340.0, $fx->buyRate());
        $this->assertTrue($fx->isAvailable());
    }

    public function test_consumer_reads_published_rates_without_mevon_http(): void
    {
        Http::fake(function () {
            $this->fail('Consumer FX must not call Mevon when published rates exist.');
        });

        Setting::set('virtual_card_fx_published_mid', 1400, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_published_sell_rate', 1415, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_published_buy_rate', 1370, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_published_source', 'mevon_live', 'string', 'virtual_card', 'test');

        $fx = app(VirtualCardFxService::class);

        $this->assertSame(1400.0, $fx->midUsdNgnRate());
        $this->assertSame(1415.0, $fx->sellRate());
        $this->assertSame(1370.0, $fx->buyRate());
    }

    public function test_publish_from_cashwyre_writes_settings_for_consumer(): void
    {
        Cache::flush();

        Http::fake([
            'https://cashwyre.test/api/v1.0/businessRate/getFxRates' => Http::response([
                'success' => true,
                'message' => 'Request Successful',
                'data' => [
                    [
                        'currency' => 'NGN',
                        'buyRate' => 1380,
                        'sellRate' => 1415,
                    ],
                ],
            ], 200),
        ]);

        Setting::set('virtual_card_fx_sell_profit_ngn', 15, 'float', 'virtual_card', 'test');
        Setting::set('virtual_card_fx_buy_profit_ngn', 30, 'float', 'virtual_card', 'test');

        $publish = app(VirtualCardFxPublishService::class);
        $result = $publish->syncFromCashwyre();

        $this->assertTrue($result['ok']);
        $this->assertSame(1397.5, $result['mid']);
        $this->assertSame(1430.0, $result['sell_rate']);
        $this->assertSame(1350.0, $result['buy_rate']);
        $this->assertSame('cashwyre_live', $result['source']);
    }
}
