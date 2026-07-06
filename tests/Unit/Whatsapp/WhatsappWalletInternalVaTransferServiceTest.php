<?php

namespace Tests\Unit\Whatsapp;

use App\Models\WhatsappWallet;
use App\Services\Whatsapp\WhatsappWalletInternalVaTransferService;
use Tests\TestCase;

class WhatsappWalletInternalVaTransferServiceTest extends TestCase
{
    public function test_normalize_account_strips_non_digits(): void
    {
        $this->assertSame('1234567890', WhatsappWalletInternalVaTransferService::normalizeAccountNumber('1234-567-890'));
    }

    public function test_resolve_recipient_returns_null_for_empty_account(): void
    {
        $wallet = new WhatsappWallet(['id' => 1]);
        $wallet->exists = true;

        $service = app(WhatsappWalletInternalVaTransferService::class);

        $this->assertNull($service->resolveRecipientWallet($wallet, ''));
    }

    public function test_is_own_tier2_va_matches_wallet_permanent_account(): void
    {
        $wallet = new WhatsappWallet([
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'mevon_virtual_account_number' => '8012345678',
        ]);

        $service = app(WhatsappWalletInternalVaTransferService::class);

        $this->assertTrue($service->isOwnTier2Va($wallet, '8012345678'));
        $this->assertFalse($service->isOwnTier2Va($wallet, '8098765432'));
    }

    public function test_is_own_tier2_va_false_for_tier1_wallet(): void
    {
        $wallet = new WhatsappWallet([
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'mevon_virtual_account_number' => '8012345678',
        ]);

        $service = app(WhatsappWalletInternalVaTransferService::class);

        $this->assertFalse($service->isOwnTier2Va($wallet, '8012345678'));
    }
}
