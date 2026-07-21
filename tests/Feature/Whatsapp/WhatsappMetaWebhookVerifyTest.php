<?php

namespace Tests\Feature\Whatsapp;

use Tests\TestCase;

class WhatsappMetaWebhookVerifyTest extends TestCase
{
    public function test_meta_webhook_verification_echoes_challenge(): void
    {
        config([
            'whatsapp.provider' => 'cloud',
            'whatsapp.cloud.verify_token' => 'test-verify-token',
        ]);

        $response = $this->get('/api/v1/whatsapp/webhook?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'test-verify-token',
            'hub_challenge' => '1234567890',
        ]));

        $response->assertOk();
        $response->assertSee('1234567890');
    }

    public function test_meta_webhook_verification_rejects_bad_token(): void
    {
        config([
            'whatsapp.provider' => 'cloud',
            'whatsapp.cloud.verify_token' => 'test-verify-token',
        ]);

        $response = $this->get('/api/v1/whatsapp/webhook?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong-token',
            'hub_challenge' => '1234567890',
        ]));

        $response->assertForbidden();
    }
}
