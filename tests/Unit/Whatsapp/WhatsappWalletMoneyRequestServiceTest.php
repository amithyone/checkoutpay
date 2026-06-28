<?php

namespace Tests\Unit\Whatsapp;

use App\Models\Setting;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\WhatsappWalletMoneyRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappWalletMoneyRequestServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
        Config::set([
            'consumer_wallet.money_request_enabled' => true,
            'consumer_wallet.credit_push_enabled' => false,
            'whatsapp.evolution.wallet_instance' => 'test-instance',
        ]);
        Setting::set('savings_enabled', false, 'boolean', 'savings');
        $this->mock(EvolutionWhatsAppClient::class, function ($mock) {
            $mock->shouldReceive('sendText')->andReturn(true);
        });
    }

    public function test_create_returns_sufficiency_hint_when_payer_balance_low_and_hints_enabled(): void
    {
        $requester = $this->wallet('2348011111111', 1000);
        $this->wallet('2348022222222', 100, senderName: 'Ada Okoro');

        $service = app(WhatsappWalletMoneyRequestService::class);
        $result = $service->create($requester, '+2348022222222', 5000);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Ada Okoro', $result['message']);
        $this->assertStringContainsString('sent your request', $result['message']);
    }

    public function test_create_hides_balance_when_payer_opted_out(): void
    {
        $requester = $this->wallet('2348011111111', 1000);
        $payer = $this->wallet('2348022222222', 100, senderName: 'Ada Okoro');
        $payer->money_request_balance_hint_enabled = false;
        $payer->save();

        $service = app(WhatsappWalletMoneyRequestService::class);
        $result = $service->create($requester, '+2348022222222', 5000);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Request sent', $result['message']);
        $this->assertStringNotContainsString("doesn't have", $result['message']);
    }

    private function ensureSchema(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');

        if (! $schema->hasTable('whatsapp_wallets')) {
            $schema->create('whatsapp_wallets', function (Blueprint $table) {
                $table->id();
                $table->string('phone_e164', 32)->unique();
                $table->string('sender_name', 128)->nullable();
                $table->string('pin_hash')->nullable();
                $table->unsignedTinyInteger('tier')->default(1);
                $table->decimal('balance', 14, 2)->default(0);
                $table->boolean('money_request_balance_hint_enabled')->default(true);
                $table->string('status', 32)->default('active');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('whatsapp_wallet_money_requests')) {
            $schema->create('whatsapp_wallet_money_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('requester_wallet_id');
                $table->string('requester_phone_e164', 32);
                $table->string('payer_phone_e164', 32);
                $table->unsignedBigInteger('payer_wallet_id')->nullable();
                $table->decimal('amount', 14, 2);
                $table->string('currency', 8)->default('NGN');
                $table->string('note', 140)->nullable();
                $table->string('status', 24)->default('pending');
                $table->string('channel', 24)->default('consumer_api');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->unsignedBigInteger('p2p_debit_transaction_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('settings')) {
            $schema->create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type', 32)->default('string');
                $table->string('group', 64)->default('general');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }

    private function wallet(string $e164, float $balance, ?string $senderName = null): WhatsappWallet
    {
        return WhatsappWallet::query()->create([
            'phone_e164' => $e164,
            'sender_name' => $senderName,
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => $balance,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'money_request_balance_hint_enabled' => true,
        ]);
    }
}
