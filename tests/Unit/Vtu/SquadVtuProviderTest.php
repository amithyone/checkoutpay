<?php

namespace Tests\Unit\Vtu;

use App\Services\Squad\SquadVtuApiClient;
use App\Services\Vtu\SquadVtuProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SquadVtuProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'squad_vtu.enabled' => true,
            'squad_vtu.base_url' => 'https://sandbox-api-d.squadco.com',
            'squad_vtu.secret_key' => 'sandbox_sk_test',
            'squad_vtu.airtime_min' => 50,
            'squad_vtu.airtime_max' => 50000,
            'squad_vtu.data_plans_cache_seconds' => 60,
        ]);
    }

    public function test_purchase_airtime_posts_naira_amount(): void
    {
        Http::fake([
            'https://sandbox-api-d.squadco.com/vending/purchase/airtime' => Http::response([
                'status' => 200,
                'success' => true,
                'message' => 'Success',
                'data' => [
                    'reference' => 'ref-air-1',
                    'amount' => '100.00',
                    'status' => 'pending',
                    'meta' => '{"vending_status":"pending"}',
                ],
            ], 200),
        ]);

        $result = app(SquadVtuProvider::class)->purchaseAirtime('mtn', '08139011943', 100);

        $this->assertTrue($result['ok']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox-api-d.squadco.com/vending/purchase/airtime'
                && $request['phone_number'] === '08139011943'
                && $request['amount'] === 100
                && str_contains((string) $request->header('Authorization')[0], 'sandbox_sk_test');
        });
    }

    public function test_fetch_data_plans_maps_squad_bundles(): void
    {
        Http::fake([
            'https://sandbox-api-d.squadco.com/vending/data-bundles*' => Http::response([
                'status' => 200,
                'success' => true,
                'message' => 'Success',
                'data' => [
                    [
                        'plan_name' => 'MTN data_plan',
                        'bundle_value' => '100MB',
                        'bundle_validity' => 'Daily Plan',
                        'bundle_price' => '100',
                        'plan_code' => '1001',
                        'network' => 'MTN',
                    ],
                ],
            ], 200),
        ]);

        $result = app(SquadVtuProvider::class)->fetchDataPlans('mtn');

        $this->assertTrue($result['ok']);
        $this->assertSame('1001', $result['plans'][0]['variation_id']);
        $this->assertSame(100.0, $result['plans'][0]['price']);
        $this->assertStringContainsString('100MB', $result['plans'][0]['label']);
    }

    public function test_purchase_data_sends_plan_code(): void
    {
        Http::fake([
            'https://sandbox-api-d.squadco.com/vending/purchase/data' => Http::response([
                'status' => 200,
                'success' => true,
                'message' => 'Success',
                'data' => [
                    'reference' => 'ref-data-1',
                    'status' => 'success',
                ],
            ], 200),
        ]);

        $result = app(SquadVtuProvider::class)->purchaseData('mtn', '07062918558', '1001', 100);

        $this->assertTrue($result['ok']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/vending/purchase/data')
                && $request['plan_code'] === '1001'
                && $request['amount'] === 100;
        });
    }

    public function test_client_not_configured_without_secret(): void
    {
        config(['squad_vtu.secret_key' => '']);
        $this->assertFalse(app(SquadVtuApiClient::class)->isConfigured());
    }
}
