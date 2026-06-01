<?php

namespace Tests\Unit\Whatsapp;

use App\Services\Whatsapp\WhatsappWalletNameMatcher;
use Tests\TestCase;

class WhatsappWalletNameMatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.wallet.pin_reset_name_min_score' => 60]);
    }

    public function test_daniel_joseph_matches_bank_name(): void
    {
        $this->assertTrue(WhatsappWalletNameMatcher::passes(
            'DANIEL DAVID JOSEPH',
            'daniel joseph'
        ));
    }

    public function test_obvious_mismatch_fails(): void
    {
        $this->assertFalse(WhatsappWalletNameMatcher::passes(
            'John Smith',
            'Ada Okafor'
        ));
    }

    public function test_bidirectional_scoring(): void
    {
        $this->assertTrue(WhatsappWalletNameMatcher::passes(
            'daniel joseph',
            'DANIEL DAVID JOSEPH'
        ));
    }
}
