<?php

namespace Tests\Unit\Consumer;

use App\Models\VirtualCardRequest;
use App\Models\WhatsappWallet;
use App\Services\Consumer\VirtualCardProviderResponseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualCardProviderResponseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_async_mevon_message_is_treated_as_accepted(): void
    {
        $svc = app(VirtualCardProviderResponseService::class);

        $api = [
            'ok' => false,
            'message' => 'Card creation request processed successfully',
            'http_status' => 200,
            'raw' => ['status' => false, 'message' => 'Card creation request processed successfully'],
        ];

        $this->assertTrue($svc->isCreateAccepted($api));
    }

    public function test_apply_accepted_without_card_id_sets_preparing(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348148790999',
            'display_name' => 'Card Test',
            'balance' => 10000,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
        ]);

        $row = VirtualCardRequest::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'status' => VirtualCardRequest::STATUS_PENDING,
            'fee_usd' => 5,
            'fee_ngn' => 6925,
            'external_reference' => 'VCARD-TEST',
        ]);

        $svc = app(VirtualCardProviderResponseService::class);
        $fresh = $svc->applyAccepted($row, [
            'ok' => false,
            'message' => 'Card creation request processed successfully',
            'raw' => ['status' => false],
        ]);

        $this->assertSame(VirtualCardRequest::STATUS_PREPARING, $fresh->status);
        $this->assertNull($fresh->card_external_id);
    }

    public function test_extract_provider_reference_accepts_mevon_req_format(): void
    {
        $svc = app(VirtualCardProviderResponseService::class);

        $ref = $svc->extractProviderReference([
            'ok' => false,
            'message' => 'Card creation request processed successfully',
            'raw' => [
                'status' => false,
                'data' => ['request_id' => 'REQ1780744493644'],
            ],
        ]);

        $this->assertSame('REQ1780744493644', $ref);
    }
}
