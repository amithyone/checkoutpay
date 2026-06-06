<?php

namespace Tests\Unit\Consumer;

use App\Models\VirtualCardRequest;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\VirtualCardFeeRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualCardFeeRefundServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_fee_collected_redebits_refunded_fee_once(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345678',
            'display_name' => 'Refund Recovery',
            'balance' => 50000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $row = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_FAILED,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-REFUND-RECOVER',
        ]);

        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'sender_name' => 'Refund Recovery',
            'type' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
            'amount' => 6925,
            'balance_after' => 43075,
            'external_reference' => $row->external_reference,
            'meta' => [
                'refunded' => true,
                'refund_reason' => 'provider_failed',
            ],
        ]);

        $wallet->update(['balance' => 50000]);

        $svc = app(VirtualCardFeeRefundService::class);
        $first = $svc->ensureFeeCollectedForActivation($row);
        $this->assertTrue($first['ok']);
        $this->assertTrue($first['collected'] ?? false);

        $wallet->refresh();
        $this->assertSame(43075.0, (float) $wallet->balance);

        $second = $svc->ensureFeeCollectedForActivation($row->fresh());
        $this->assertTrue($second['ok']);
        $this->assertTrue($second['already_collected'] ?? false);

        $wallet->refresh();
        $this->assertSame(43075.0, (float) $wallet->balance);
    }

    public function test_ensure_fee_collected_skips_when_fee_still_held(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348098765432',
            'display_name' => 'Held Fee',
            'balance' => 43075,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $row = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_PREPARING,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-HELD-FEE',
        ]);

        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'sender_name' => 'Held Fee',
            'type' => WhatsappWalletTransaction::TYPE_VIRTUAL_CARD_FEE,
            'amount' => 6925,
            'balance_after' => 43075,
            'external_reference' => $row->external_reference,
            'meta' => ['channel' => 'consumer_api'],
        ]);

        $svc = app(VirtualCardFeeRefundService::class);
        $result = $svc->ensureFeeCollectedForActivation($row);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['already_collected'] ?? false);

        $wallet->refresh();
        $this->assertSame(43075.0, (float) $wallet->balance);
    }
}
