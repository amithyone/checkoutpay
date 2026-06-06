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
        $this->assertTrue($svc->isInsufficientUsdError('Merchant USD balance too low'));
        $this->assertTrue($svc->isInsufficientUsdError('Not enough USD in float'));
        $this->assertFalse($svc->isInsufficientUsdError('Invalid PIN'));
    }

    public function test_force_top_up_buys_usd_when_wallet_balance_is_short(): void
    {
        config(['virtual_card.auto_fund_force_buy_usd' => 2]);

        Http::fake([
            'https://mevon.test/V1/balance' => Http::sequence()
                ->push([
                    'status' => 'success',
                    'data' => ['bal' => '500000', 'usd_balance' => '3.00'],
                ], 200)
                ->push([
                    'status' => 'success',
                    'data' => ['bal' => '500000', 'usd_balance' => '3.00'],
                ], 200)
                ->push([
                    'status' => 'success',
                    'data' => ['bal' => '491600', 'usd_balance' => '9.00'],
                ], 200),
            'https://mevon.test/V1/exchange' => Http::response([
                'status' => true,
                'message' => 'Conversion successful',
                'data' => [
                    'from_currency' => 'NGN',
                    'to_currency' => 'USD',
                    'amount' => 8400,
                    'converted_amount' => 6,
                    'new_usd_balance' => 9,
                ],
            ], 200),
        ]);

        $result = app(MevonPayUsdAutoFundService::class)->fundAfterProviderInsufficientUsd(5, 'test_force');

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['funded']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/V1/exchange'));
    }

    public function test_skips_conversion_when_wallet_usd_covers_amount_even_if_ledger_is_zero(): void
    {
        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'data' => [
                    'bal' => '500000',
                    'usd_balance' => '32.31',
                    'usd_ledger_bal' => '0.00',
                ],
            ], 200),
        ]);

        $result = app(MevonPayUsdAutoFundService::class)->ensureUsdBalance(10, 'test_skip');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['funded']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/V1/exchange'));
    }

    public function test_provider_retry_skips_ngn_conversion_when_wallet_usd_is_already_enough(): void
    {
        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'data' => [
                    'bal' => '500000',
                    'usd_balance' => '32.31',
                    'usd_ledger_bal' => '0.00',
                ],
            ], 200),
        ]);

        $result = app(MevonPayUsdAutoFundService::class)->fundAfterProviderInsufficientUsd(10, 'test_retry_skip');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['funded']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/V1/exchange'));
    }
}
