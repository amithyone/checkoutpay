<?php

namespace Tests\Unit\Whatsapp;

use App\Models\WhatsappWallet;
use App\Services\Whatsapp\WhatsappWalletCasualMoneyRequestParser;
use Tests\TestCase;

class WhatsappWalletCasualMoneyRequestParserTest extends TestCase
{
    private function wallet(): WhatsappWallet
    {
        return new WhatsappWallet([
            'phone_e164' => '2348011111111',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 50000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);
    }

    public function test_need_amount_from_phone(): void
    {
        $parsed = WhatsappWalletCasualMoneyRequestParser::tryParse(
            'need 5000 from 08031234567',
            $this->wallet(),
        );

        $this->assertNotNull($parsed);
        $this->assertSame(5000.0, $parsed['amount']);
        $this->assertStringContainsString('8031234567', $parsed['payer_phone_e164']);
    }

    public function test_request_5k_from_phone(): void
    {
        $parsed = WhatsappWalletCasualMoneyRequestParser::tryParse(
            'request 5k from 08031234567',
            $this->wallet(),
        );

        $this->assertNotNull($parsed);
        $this->assertSame(5000.0, $parsed['amount']);
    }

    public function test_ask_phone_for_amount(): void
    {
        $parsed = WhatsappWalletCasualMoneyRequestParser::tryParse(
            'ask 08031234567 for 2000',
            $this->wallet(),
        );

        $this->assertNotNull($parsed);
        $this->assertSame(2000.0, $parsed['amount']);
    }

    public function test_send_phrase_is_not_money_request(): void
    {
        $this->assertNull(WhatsappWalletCasualMoneyRequestParser::tryParse(
            'send 5000 to 08031234567',
            $this->wallet(),
        ));
    }
}
