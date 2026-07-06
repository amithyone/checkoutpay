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
}
