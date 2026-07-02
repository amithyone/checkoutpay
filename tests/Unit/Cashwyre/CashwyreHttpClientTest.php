<?php

namespace Tests\Unit\Cashwyre;

use App\Services\Cashwyre\CashwyreHttpClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CashwyreHttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cashwyre.base_url' => 'https://businessapi.cashwyre.com/api/v1.0',
            'cashwyre.app_id' => 'APP123',
            'cashwyre.business_code' => 'APP123',
            'cashwyre.secret_key' => 'secK_test_secret',
        ]);
    }

    public function test_is_configured_requires_secret_app_and_business_code(): void
    {
        $client = app(CashwyreHttpClient::class);
        $this->assertTrue($client->isConfigured());

        config(['cashwyre.secret_key' => '']);
        $this->assertFalse(app(CashwyreHttpClient::class)->isConfigured());
    }

    public function test_with_base_payload_merges_required_fields(): void
    {
        $client = app(CashwyreHttpClient::class);
        $payload = $client->withBasePayload(['cardCode' => 'VCARD123'], 'req-001');

        $this->assertSame('APP123', $payload['appId']);
        $this->assertSame('APP123', $payload['businessCode']);
        $this->assertSame('req-001', $payload['requestId']);
        $this->assertSame('VCARD123', $payload['cardCode']);
    }

    public function test_post_json_uses_bearer_secret_auth(): void
    {
        Http::fake([
            'businessapi.cashwyre.com/*' => Http::response([
                'success' => true,
                'message' => 'Result successful',
                'data' => ['code' => 'VCARD999'],
            ], 200),
        ]);

        $response = app(CashwyreHttpClient::class)->postJson('/CustomerCard/getCard', [
            'cardCode' => 'VCARD999',
        ], 'req-get-card');

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer secK_test_secret')
                && ! $request->hasHeader('X-API-Key')
                && ($request->data()['appId'] ?? null) === 'APP123'
                && ($request->data()['businessCode'] ?? null) === 'APP123'
                && ($request->data()['requestId'] ?? null) === 'req-get-card'
                && ($request->data()['cardCode'] ?? null) === 'VCARD999';
        });

        $this->assertTrue($response['ok']);
        $this->assertSame('VCARD999', $response['data']['code']);
    }
}
