<?php

namespace Tests\Unit\Consumer;

use App\Models\Setting;
use App\Models\WalletSaveTogetherMember;
use App\Models\WalletSaveTogetherPot;
use App\Models\WhatsappWallet;
use App\Services\Consumer\SaveTogetherService;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaveTogetherServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
        Config::set([
            'consumer_wallet.save_together_enabled' => true,
            'consumer_wallet.credit_push_enabled' => false,
            'whatsapp.evolution.wallet_instance' => 'test-instance',
        ]);
        Setting::set('savings_enabled', false, 'boolean', 'savings');
        $this->mock(EvolutionWhatsAppClient::class, function ($mock) {
            $mock->shouldReceive('sendText')->andReturn(true);
        });
    }

    public function test_create_splits_target_equally(): void
    {
        $creator = $this->wallet('2348011111111', 5000, 'Creator');
        $this->wallet('2348022222222', 1000);
        $this->wallet('2348033333333', 1000);

        $service = app(SaveTogetherService::class);
        $result = $service->create(
            $creator,
            'Holiday',
            1200,
            ['+2348022222222', '+2348033333333'],
            WalletSaveTogetherPot::MODE_FULL_CONTRIBUTION,
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(400.0, (float) ($result['data']['per_member_share'] ?? 0));
        $this->assertSame(3, (int) ($result['data']['member_count'] ?? 0));
    }

    public function test_multiple_partial_contributions_until_share_met(): void
    {
        $creator = $this->wallet('2348011111111', 5000);
        $this->wallet('2348022222222', 5000);

        $service = app(SaveTogetherService::class);
        $create = $service->create($creator, 'Test', 1000, ['+2348022222222'], WalletSaveTogetherPot::MODE_FULL_CONTRIBUTION);
        $potId = (string) $create['data']['id'];

        $r1 = $service->contribute($creator, $potId, 200);
        $this->assertTrue($r1['ok']);
        $this->assertSame(300.0, (float) ($r1['data']['my_remaining_share'] ?? 0));

        $r2 = $service->contribute($creator, $potId, 300);
        $this->assertTrue($r2['ok']);

        $over = $service->contribute($creator, $potId, 50);
        $this->assertFalse($over['ok']);
    }

    public function test_process_deadlines_unlocks_time_mode_pot(): void
    {
        $creator = $this->wallet('2348011111111', 5000);
        $this->wallet('2348022222222', 1000);
        $service = app(SaveTogetherService::class);
        $create = $service->create(
            $creator,
            'Timed',
            1000,
            ['+2348022222222'],
            WalletSaveTogetherPot::MODE_TIME_DEADLINE,
            now()->addDays(7),
        );
        $this->assertTrue($create['ok']);
        $potId = (string) $create['data']['id'];
        $service->contribute($creator, $potId, 100);

        $pot = WalletSaveTogetherPot::query()->where('public_id', $potId)->first();
        $this->assertSame(WalletSaveTogetherPot::STATUS_COLLECTING, $pot->status);
        $pot->deadline_at = now()->subMinute();
        $pot->save();

        $service->processDeadlines();
        $pot->refresh();
        $this->assertSame(WalletSaveTogetherPot::STATUS_UNLOCKED, $pot->status);
    }

    public function test_invite_records_activity_and_sends_whatsapp(): void
    {
        $sent = [];
        $this->mock(EvolutionWhatsAppClient::class, function ($mock) use (&$sent) {
            $mock->shouldReceive('sendText')->andReturnUsing(function ($instance, $phone, $text) use (&$sent) {
                $sent[] = ['phone' => $phone, 'text' => $text];

                return true;
            });
        });

        $creator = $this->wallet('2348011111111', 5000, 'Creator');
        $member = $this->wallet('2348022222222', 1000, 'Member');

        $service = app(SaveTogetherService::class);
        $result = $service->create($creator, 'Trip', 1000, ['+2348022222222'], WalletSaveTogetherPot::MODE_FULL_CONTRIBUTION);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $sent);
        $this->assertSame('2348022222222', $sent[0]['phone']);
        $this->assertStringContainsString('Creator', $sent[0]['text']);
        $this->assertStringContainsString('Trip', $sent[0]['text']);

        $this->assertSame(
            1,
            \App\Models\WhatsappWalletTransaction::query()
                ->where('whatsapp_wallet_id', $member->id)
                ->where('type', \App\Models\WhatsappWalletTransaction::TYPE_SAVE_TOGETHER_INVITE)
                ->count(),
        );
    }

    public function test_declined_pot_still_listed_for_member(): void
    {
        $creator = $this->wallet('2348011111111', 5000);
        $member = $this->wallet('2348022222222', 5000);

        $service = app(SaveTogetherService::class);
        $create = $service->create($creator, 'Test', 1000, ['+2348022222222'], WalletSaveTogetherPot::MODE_FULL_CONTRIBUTION);
        $potId = (string) $create['data']['id'];

        $decline = $service->decline($member, $potId);
        $this->assertTrue($decline['ok']);

        $listed = $service->listForWallet($member);
        $this->assertCount(1, $listed);
        $this->assertSame(WalletSaveTogetherMember::STATUS_DECLINED, $listed[0]['my_status']);

        $this->assertSame(
            1,
            \App\Models\WhatsappWalletTransaction::query()
                ->where('whatsapp_wallet_id', $member->id)
                ->where('type', \App\Models\WhatsappWalletTransaction::TYPE_SAVE_TOGETHER_DECLINE)
                ->count(),
        );
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
                $table->decimal('daily_transfer_total', 14, 2)->default(0);
                $table->date('daily_transfer_for_date')->nullable();
                $table->unsignedTinyInteger('pin_failed_attempts')->default(0);
                $table->string('status', 32)->default('active');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('wallet_save_together_pots')) {
            $schema->create('wallet_save_together_pots', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('creator_wallet_id');
                $table->string('title', 120);
                $table->decimal('target_amount', 14, 2);
                $table->decimal('per_member_share', 14, 2);
                $table->decimal('total_contributed', 14, 2)->default(0);
                $table->string('completion_mode', 32);
                $table->timestamp('deadline_at')->nullable();
                $table->string('status', 24)->default('collecting');
                $table->timestamp('unlocked_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->string('currency', 8)->default('NGN');
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('wallet_save_together_members')) {
            $schema->create('wallet_save_together_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pot_id');
                $table->unsignedBigInteger('wallet_id')->nullable();
                $table->string('phone_e164', 32);
                $table->string('display_name', 128)->nullable();
                $table->string('role', 16)->default('member');
                $table->decimal('share_target', 14, 2);
                $table->decimal('contributed_amount', 14, 2)->default(0);
                $table->decimal('withdrawn_amount', 14, 2)->default(0);
                $table->string('status', 24)->default('invited');
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('first_contributed_at')->nullable();
                $table->timestamp('share_completed_at')->nullable();
                $table->timestamp('withdrawn_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('wallet_save_together_contributions')) {
            $schema->create('wallet_save_together_contributions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pot_id');
                $table->unsignedBigInteger('member_id');
                $table->decimal('amount', 14, 2);
                $table->string('kind', 16);
                $table->unsignedBigInteger('whatsapp_wallet_transaction_id')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('whatsapp_wallet_transactions')) {
            $schema->create('whatsapp_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->string('sender_name', 128)->nullable();
                $table->string('type', 32);
                $table->string('ledger_scope', 16)->default('personal');
                $table->decimal('amount', 14, 2);
                $table->decimal('balance_after', 14, 2)->nullable();
                $table->string('counterparty_account_name', 128)->nullable();
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
        ]);
    }
}
