<?php

namespace Tests\Unit;

use App\Services\Whatsapp\WhatsappWalletCasualVtuParser;
use Tests\TestCase;

class WhatsappWalletCasualVtuParserTest extends TestCase
{
    public function test_shortcut_keywords(): void
    {
        $this->assertSame('airtime', WhatsappWalletCasualVtuParser::shortcutKind('AIRTIME'));
        $this->assertSame('data', WhatsappWalletCasualVtuParser::shortcutKind('DATA'));
        $this->assertSame('electricity', WhatsappWalletCasualVtuParser::shortcutKind('POWER'));
        $this->assertSame('bills', WhatsappWalletCasualVtuParser::shortcutKind('5'));
        $this->assertNull(WhatsappWalletCasualVtuParser::shortcutKind('HELLO'));
    }

    public function test_parses_airtime_with_amount_phone_and_network(): void
    {
        $parsed = WhatsappWalletCasualVtuParser::tryParse(
            'buy airtime 500 to 08031234567 mtn',
            '2348012345678'
        );
        $this->assertNotNull($parsed);
        $this->assertSame('airtime', $parsed['kind']);
        $this->assertSame(500.0, $parsed['amount']);
        $this->assertSame('mtn', $parsed['network_id']);
        $this->assertSame('2348031234567', $parsed['recipient_e164']);
    }

    public function test_parses_airtime_with_k_suffix(): void
    {
        $parsed = WhatsappWalletCasualVtuParser::tryParse(
            'buy 1k airtime for 08031234567 glo',
            '2348012345678'
        );
        $this->assertNotNull($parsed);
        $this->assertSame('airtime', $parsed['kind']);
        $this->assertSame(1000.0, $parsed['amount']);
        $this->assertSame('glo', $parsed['network_id']);
    }

    public function test_parses_data_intent(): void
    {
        $parsed = WhatsappWalletCasualVtuParser::tryParse(
            'buy 2gb data to 08031234567 airtel',
            '2348012345678'
        );
        $this->assertNotNull($parsed);
        $this->assertSame('data', $parsed['kind']);
        $this->assertSame('airtel', $parsed['network_id']);
        $this->assertSame('2gb', $parsed['data_plan_hint'] ?? null);
    }

    public function test_parses_electricity_intent(): void
    {
        $parsed = WhatsappWalletCasualVtuParser::tryParse(
            'pay electricity 5000 meter 12345678901 ikeja prepaid',
            '2348012345678'
        );
        $this->assertNotNull($parsed);
        $this->assertSame('electricity', $parsed['kind']);
        $this->assertSame(5000.0, $parsed['amount']);
        $this->assertSame('12345678901', $parsed['meter']);
        $this->assertSame('prepaid', $parsed['meter_type']);
    }

    public function test_vague_bill_request_returns_null_for_try_parse(): void
    {
        $this->assertNull(WhatsappWalletCasualVtuParser::tryParse('buy airtime please', '2348012345678'));
        $this->assertTrue(WhatsappWalletCasualVtuParser::looksLikeCasualBill('buy airtime please'));
    }
}
