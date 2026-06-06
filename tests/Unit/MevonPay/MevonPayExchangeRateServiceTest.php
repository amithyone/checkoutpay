<?php

namespace Tests\Unit\MevonPay;

use App\Services\MevonPay\MevonPayExchangeRateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MevonPayExchangeRateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'test-secret',
            'mevonpay_vtu.paths.exchange' => '/V1/exchange',
            'virtual_card.mevon_rate_cache_seconds' => 600,
        ]);
    }

    public function test_reads_ngn_per_usd_from_exchange_response(): void
    {
        Http::fake([
            'https://mevon.test/V1/exchange' => Http::response([
                'status' => true,
                'message' => 'Conversion successful',
                'data' => [
                    'from_currency' => 'NGN',
                    'to_currency' => 'USD',
                    'amount' => 1,
                    'rate' => '1370.1200',
                    'converted_amount' => 0.0007,
                ],
            ], 200),
        ]);

        $rate = app(MevonPayExchangeRateService::class)->ngnPerUsd();

        $this->assertSame(1370.12, $rate);
    }
}
