<?php

namespace Tests\Unit\Whatsapp;

use App\Models\WhatsappWallet;
use Tests\TestCase;

class WhatsappWalletTier2NameSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['whatsapp.wallet.pin_reset_name_min_score' => 60]);
    }

    public function test_replaces_nickname_with_bank_verified_name(): void
    {
        $wallet = new WhatsappWallet(['sender_name' => 'Bob']);

        $this->assertSame(
            'DANIEL DAVID JOSEPH',
            $wallet->resolveSenderNameAfterTier2('DANIEL DAVID JOSEPH', 'Daniel', 'Joseph'),
        );
    }

    public function test_keeps_sender_name_when_it_matches_verified_name(): void
    {
        $wallet = new WhatsappWallet(['sender_name' => 'Daniel Joseph']);

        $this->assertNull(
            $wallet->resolveSenderNameAfterTier2('DANIEL DAVID JOSEPH', 'Daniel', 'Joseph'),
        );
    }

    public function test_sets_verified_name_when_sender_name_empty(): void
    {
        $wallet = new WhatsappWallet(['sender_name' => null]);

        $this->assertSame(
            'ADA OKORO',
            $wallet->resolveSenderNameAfterTier2('ADA OKORO', 'Ada', 'Okoro'),
        );
    }

    public function test_falls_back_to_kyc_name_when_account_name_missing(): void
    {
        $wallet = new WhatsappWallet(['sender_name' => 'Mike']);

        $this->assertSame(
            'Chidi Nwosu',
            $wallet->resolveSenderNameAfterTier2('', 'Chidi', 'Nwosu'),
        );
    }
}
