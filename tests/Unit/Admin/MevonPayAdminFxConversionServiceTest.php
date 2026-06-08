<?php

namespace Tests\Unit\Admin;

use App\Services\Admin\MevonPayAdminFxConversionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MevonPayAdminFxConversionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'test-secret',
            'mevonpay_vtu.paths.balance' => '/V1/balance',
            'mevonpay_vtu.paths.exchange' => '/V1/exchange',
            'virtual_card.auto_fund_usd_max_per_op' => 500,
            'virtual_card.auto_fund_ngn_per_usd' => 1400,
            'virtual_card.auto_fund_ngn_buffer_percent' => 0,
            'virtual_card.mevon_rate_cache_seconds' => 60,
        ]);
    }

    public function test_buy_usd_converts_ngn_to_usd(): void
    {
        Http::fake([
            'https://mevon.test/V1/balance' => Http::sequence()
                ->push([
                    'status' => 'success',
                    'data' => ['bal' => '500000', 'usd_balance' => '5.00'],
                ], 200)
                ->push([
                    'status' => 'success',
                    'data' => ['bal' => '486000', 'usd_balance' => '15.00'],
                ], 200),
            'https://mevon.test/V1/exchange' => Http::response([
                'status' => true,
                'data' => [
                    'from_currency' => 'NGN',
                    'to_currency' => 'USD',
                    'amount' => 14000,
                    'converted_amount' => 10,
                    'rate' => 1400,
                ],
            ], 200),
        ]);

        $result = app(MevonPayAdminFxConversionService::class)->buyUsd(10, 1);

        $this->assertTrue($result['ok']);
        $this->assertSame('buy', $result['direction']);
        $this->assertEquals(10.0, $result['usd_amount']);
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/V1/exchange')) {
                return false;
            }
            $body = $request->data();

            return ($body['from_currency'] ?? null) === 'NGN'
                && ($body['to_currency'] ?? null) === 'USD'
                && ($body['amount'] ?? 0) > 0;
        });
    }

    public function test_sell_usd_converts_usd_to_ngn(): void
    {
        Http::fake([
            'https://mevon.test/V1/balance' => Http::sequence()
                ->push([
                    'status' => 'success',
                    'data' => ['bal' => '100000', 'usd_balance' => '25.00'],
                ], 200)
                ->push([
                    'status' => 'success',
                    'data' => ['bal' => '114000', 'usd_balance' => '15.00'],
                ], 200),
            'https://mevon.test/V1/exchange' => Http::response([
                'status' => true,
                'data' => [
                    'from_currency' => 'USD',
                    'to_currency' => 'NGN',
                    'amount' => 10,
                    'converted_amount' => 14000,
                    'rate' => 1400,
                ],
            ], 200),
        ]);

        $result = app(MevonPayAdminFxConversionService::class)->sellUsd(10, 1);

        $this->assertTrue($result['ok']);
        $this->assertSame('sell', $result['direction']);
        $this->assertEquals(10.0, $result['usd_amount']);
        $this->assertEquals(14000.0, $result['ngn_amount']);
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/V1/exchange')) {
                return false;
            }
            $body = $request->data();

            return ($body['from_currency'] ?? null) === 'USD'
                && ($body['to_currency'] ?? null) === 'NGN'
                && (float) ($body['amount'] ?? 0) === 10.0;
        });
    }

    public function test_buy_usd_rejects_insufficient_ngn(): void
    {
        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'data' => ['bal' => '1000', 'usd_balance' => '0.00'],
            ], 200),
        ]);

        $result = app(MevonPayAdminFxConversionService::class)->buyUsd(50);

        $this->assertFalse($result['ok']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/V1/exchange'));
    }

    public function test_sell_usd_rejects_insufficient_usd(): void
    {
        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'data' => ['bal' => '500000', 'usd_balance' => '2.00'],
            ], 200),
        ]);

        $result = app(MevonPayAdminFxConversionService::class)->sellUsd(10);

        $this->assertFalse($result['ok']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/V1/exchange'));
    }
}
