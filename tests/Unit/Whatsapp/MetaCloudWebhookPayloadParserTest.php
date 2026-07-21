<?php

namespace Tests\Unit\Whatsapp;

use App\Services\Whatsapp\MetaCloudWebhookPayloadParser;
use Illuminate\Http\Request;
use Tests\TestCase;

class MetaCloudWebhookPayloadParserTest extends TestCase
{
    public function test_extracts_text_message_from_cloud_payload(): void
    {
        config([
            'whatsapp.provider' => 'cloud',
            'whatsapp.cloud.phone_number_id' => '999888777',
            'whatsapp.evolution.instance' => 'Checkout',
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => '999888777'],
                        'messages' => [[
                            'from' => '2348012345678',
                            'id' => 'wamid.test',
                            'timestamp' => '1710000000',
                            'type' => 'text',
                            'text' => ['body' => 'WALLET'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $rows = app(MetaCloudWebhookPayloadParser::class)->extractInboundMessages(Request::create('/', 'POST', $payload));

        $this->assertCount(1, $rows);
        $this->assertSame('2348012345678', $rows[0]['phone_e164']);
        $this->assertSame('WALLET', $rows[0]['text']);
        $this->assertSame('Checkout', $rows[0]['instance']);
    }
}
