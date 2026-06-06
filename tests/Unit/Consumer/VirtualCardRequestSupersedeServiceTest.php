<?php

namespace Tests\Unit\Consumer;

use App\Models\VirtualCardRequest;
use App\Models\WhatsappWallet;
use App\Services\Consumer\VirtualCardRequestSupersedeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualCardRequestSupersedeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_supersede_marks_other_failed_and_open_attempts(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348099001122',
            'display_name' => 'Supersede Test',
            'balance' => 50000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $winner = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_ACTIVE,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-WINNER',
            'card_external_id' => 'MEVON-WINNER',
        ]);

        $failed = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_FAILED,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-FAILED-1',
            'failure_reason' => 'provider timeout',
        ]);

        $preparing = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_PREPARING,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-PREP-1',
        ]);

        $count = app(VirtualCardRequestSupersedeService::class)->supersedeStaleAttempts($winner);

        $this->assertSame(2, $count);

        $failed->refresh();
        $preparing->refresh();

        $this->assertStringContainsString('Superseded', (string) $failed->failure_reason);
        $this->assertSame(VirtualCardRequest::STATUS_FAILED, $preparing->status);
        $this->assertStringContainsString('Superseded', (string) $preparing->failure_reason);
    }
}
