<?php

namespace Tests\Feature\Api;

use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumerUkBusinessBankTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_uk_personal_wallet_is_blocked_from_nigerian_bank_payout(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '447776291794',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 100,
            'business_balance' => 50_000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        $result = app(ConsumerWalletTransferService::class)->bankTransfer(
            $wallet,
            1000,
            '0123456789',
            '058',
            'GTBank',
            'Ada Okafor',
            null,
            'personal',
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('linked business wallet', $result['message']);
    }

    public function test_uk_linked_business_ledger_is_not_blocked_as_foreign_phone(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '447776291794',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 100,
            'business_balance' => 50_000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        $result = app(ConsumerWalletTransferService::class)->bankTransfer(
            $wallet,
            1000,
            '0123456789',
            '058',
            'GTBank',
            'Ada Okafor',
            null,
            'business',
        );

        $this->assertStringNotContainsString('Nigeria wallet numbers', $result['message'] ?? '');
        $this->assertStringNotContainsString('linked business wallet to send naira', $result['message'] ?? '');
    }
}
