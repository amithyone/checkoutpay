<?php

namespace Tests\Feature;

use App\Support\MevonPayEgress;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MevonEgressProxyTest extends TestCase
{
    public function test_proxy_disabled_returns_404(): void
    {
        config([
            'services.mevonpay.egress_proxy_enabled' => false,
            'services.mevonpay.egress_proxy_token' => 'test-token',
        ]);

        $this->postJson('/mevon-egress/V1/balance', [], [
            MevonPayEgress::TOKEN_HEADER => 'test-token',
        ])->assertNotFound();
    }

    public function test_proxy_forwards_to_upstream_when_authorized(): void
    {
        config([
            'services.mevonpay.egress_proxy_enabled' => true,
            'services.mevonpay.egress_proxy_token' => 'test-token',
            'services.mevonpay.egress_proxy_allowed_ips' => '',
            'services.mevonpay.egress_upstream' => 'https://mevon.test',
        ]);

        Http::fake([
            'https://mevon.test/V1/balance' => Http::response([
                'status' => 'success',
                'msg' => 'ok',
                'data' => ['bal' => '100'],
            ], 200),
        ]);

        $this->postJson('/mevon-egress/V1/balance', [], [
            MevonPayEgress::TOKEN_HEADER => 'test-token',
            'Authorization' => 'secret_test',
        ])
            ->assertOk()
            ->assertJsonPath('data.bal', '100');
    }

    public function test_proxy_rejects_bad_token(): void
    {
        config([
            'services.mevonpay.egress_proxy_enabled' => true,
            'services.mevonpay.egress_proxy_token' => 'test-token',
            'services.mevonpay.egress_proxy_allowed_ips' => '',
        ]);

        $this->postJson('/mevon-egress/V1/balance', [], [
            MevonPayEgress::TOKEN_HEADER => 'wrong',
        ])->assertUnauthorized();
    }
}
