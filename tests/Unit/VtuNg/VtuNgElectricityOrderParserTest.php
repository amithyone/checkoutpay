<?php

namespace Tests\Unit\VtuNg;

use App\Services\VtuNg\VtuNgElectricityOrderParser;
use Tests\TestCase;

class VtuNgElectricityOrderParserTest extends TestCase
{
    public function test_parses_completed_requery_payload_with_token(): void
    {
        $parsed = VtuNgElectricityOrderParser::parse([
            'ok' => true,
            'data' => [
                'status' => 'completed-api',
                'request_id' => 'CP-EL-PEBKTI0QLH3R5Q',
                'order_id' => 6025742,
                'meta_data' => [
                    'electricity_token' => '6782-1401-5731-3278-6890',
                    'meter_number' => '45059940655',
                    'customer_name' => 'NNA-ENYI NWAFOR RITA',
                    'units' => '52.3',
                ],
            ],
        ]);

        $this->assertSame('completed-api', $parsed['status']);
        $this->assertSame('CP-EL-PEBKTI0QLH3R5Q', $parsed['request_id']);
        $this->assertSame(6025742, $parsed['order_id']);
        $this->assertSame('6782-1401-5731-3278-6890', $parsed['electricity_token']);
        $this->assertSame('45059940655', $parsed['meter_number']);
        $this->assertFalse(VtuNgElectricityOrderParser::shouldStayPending($parsed));
    }

    public function test_processing_status_stays_pending_without_token(): void
    {
        $parsed = VtuNgElectricityOrderParser::parse([
            'ok' => true,
            'data' => [
                'status' => 'processing-api',
                'request_id' => 'CP-EL-ABC123',
            ],
        ]);

        $this->assertTrue(VtuNgElectricityOrderParser::isProcessingStatus('processing-api'));
        $this->assertTrue(VtuNgElectricityOrderParser::shouldStayPending($parsed));
    }

    public function test_webhook_payload_is_parsed(): void
    {
        $parsed = VtuNgElectricityOrderParser::parseWebhook([
            'status' => 'completed-api',
            'request_id' => 'CP-EL-WEBHOOK1',
            'meta_data' => [
                'electricity_token' => '1111-2222-3333',
            ],
        ]);

        $this->assertSame('1111-2222-3333', $parsed['electricity_token']);
        $this->assertSame('CP-EL-WEBHOOK1', $parsed['request_id']);
    }
}
