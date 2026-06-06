<?php

namespace Tests\Unit\MevonPay;

use App\Services\MevonPay\MevonPayUsdAutoFundService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MevonPayUsdAutoFundServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'test-secret',
            'mevonpay_vtu.paths.balance' => '/V1/balance',
            'mevonpay_vtu.paths.exchange' => '/V1/exchange',
            'virtual_card.auto_fund_usd_enabled' => true,
            'virtual_card.auto_fund_usd_buffer' => 1,
            'virtual_card.auto_fund_ngn_per_usd' => 1400,
            'virtual_card.auto_fund_ngn_buffer_percent' => 0,
        ]);
    }

    public function test_skips_exchange_when_usd_balance_is_sufficient(): void
    {
        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'data' => [
                    'bal' => '100000',
                    'usd_balance' => '20.00',
                ],
            ], 200),
        ]);

        $result = app(MevonPayUsdAutoFundService::class)->ensureUsdBalance(10, 'test');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['funded']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/V1/exchange'));
    }

    public function test_converts_ngn_to_usd_when_balance_is_low(): void
    {
        Http::fake([
            'https://mevon.test/V1/balance' => Http::sequence()
                ->push([
                    'status' => 'success',
                    'data' => [
                        'bal' => '500000',
                        'usd_balance' => '0.00',
                    ],
                ], 200)
                ->push([
                    'status' => 'success',
                    'data' => [
                        'bal' => '486000',
                        'usd_balance' => '11.00',
                    ],
                ], 200),
            'https://mevon.test/V1/exchange' => Http::response([
                'status' => true,
                'message' => 'Conversion successful',
                'data' => [
                    'from_currency' => 'NGN',
                    'to_currency' => 'USD',
                    'amount' => 15400,
                    'converted_amount' => 11,
                    'new_usd_balance' => 11,
                ],
            ], 200),
        ]);

        $result = app(MevonPayUsdAutoFundService::class)->ensureUsdBalance(10, 'test');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['funded']);
        $this->assertSame(15400.0, $result['funded_ngn']);
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/V1/exchange')) {
                return false;
            }
            $data = $request->data();

            return ($data['from_currency'] ?? '') === 'NGN'
                && ($data['to_currency'] ?? '') === 'USD'
                && (float) ($data['amount'] ?? 0) === 15400.0;
        });
    }

    public function test_detects_insufficient_usd_provider_errors(): void
    {
        $svc = app(MevonPayUsdAutoFundService::class);

        $this->assertTrue($svc->isInsufficientUsdError('Insufficient USD balance'));
        $this->assertFalse($svc->isInsufficientUsdError('Invalid PIN'));
    }

    public function test_force_top_up_buys_usd_even_when_balance_api_looks_sufficient(): void
    {
        config(['virtual_card.auto_fund_force_buy_usd' => 2]);

        Http::fake([
            'https://mevon.test/V1/balance' => Http::sequence()
                ->push([
                    'status' => 'success',
                    'data' => [
                        'bal' => '500000',
                        'usd_balance' => '20.00',
                    ],
                ], 200)
                ->push([
                    'status' => 'success',
                    'data' => [
                        'bal' => '497200',
                        'usd_balance' => '22.00',
                    ],
                ], 200),
            'https://mevon.test/V1/exchange' => Http::response([
                'status' => true,
                'message' => 'Conversion successful',
                'data' => [
                    'from_currency' => 'NGN',
                    'to_currency' => 'USD',
                    'amount' => 2800,
                    'converted_amount' => 2,
                    'new_usd_balance' => 22,
                ],
            ], 200),
        ]);

        $result = app(MevonPayUsdAutoFundService::class)->fundAfterProviderInsufficientUsd(5, 'test_force');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['funded']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/V1/exchange'));
    }
}
