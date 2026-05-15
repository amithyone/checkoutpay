<?php

namespace Tests\Unit;

use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletPayCodeService;
use App\Services\Consumer\ConsumerWalletPayQrService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsumerWalletPayQrServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        Schema::dropAllTables();
        Schema::create('whatsapp_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('phone_e164', 32)->unique();
            $table->char('pay_code', 5)->nullable()->unique();
            $table->unsignedTinyInteger('tier')->default(1);
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('sender_name')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });
    }

    /** @test */
    public function build_and_resolve_round_trip(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348022222222',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'sender_name' => 'Ada Lovelace',
        ]);

        $qr = app(ConsumerWalletPayQrService::class)->buildReceiveQr($wallet);

        $this->assertStringContainsString('/pay/', $qr['qr_url']);
        $this->assertMatchesRegularExpression('/^\d{5}$/', $qr['pay_code']);

        $resolved = app(ConsumerWalletPayQrService::class)->resolveScanInput($qr['qr_url']);
        $this->assertTrue($resolved['ok']);
        $this->assertSame('p2p', $resolved['mode']);
        $this->assertSame('2348022222222', $resolved['phone_e164']);
        $this->assertSame('Ada Lovelace', $resolved['display_name']);
    }

    /** @test */
    public function resolve_rejects_tampered_signature(): void
    {
        $result = app(ConsumerWalletPayQrService::class)->resolveScanInput(
            'https://app.check-outnow.com/pay/eyJ2IjoxfQ.invalidsig'
        );

        $this->assertFalse($result['ok']);
    }

    /** @test */
    public function pay_code_service_assigns_unique_code(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348033333333',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        $code = app(ConsumerWalletPayCodeService::class)->ensureForWallet($wallet);
        $this->assertMatchesRegularExpression('/^\d{5}$/', $code);
        $wallet->refresh();
        $this->assertSame($code, $wallet->pay_code);
    }
}
