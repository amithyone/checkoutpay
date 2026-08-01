<?php

namespace Tests\Unit\Support;

use App\Models\Admin;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Support\WalletSupportStaffResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WalletSupportStaffResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
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
                $table->string('status', 32)->default('active');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumer_wallet_api_accounts')) {
            $schema->create('consumer_wallet_api_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->string('phone_e164', 32)->unique();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('admins')) {
            $schema->create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('role')->default('admin');
                $table->boolean('is_active')->default(true);
                $table->string('whatsapp_e164', 32)->nullable();
                $table->boolean('notify_wallet_signup')->default(false);
                $table->boolean('handles_wallet_support_in_app')->default(true);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('support_tickets')) {
            $schema->create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('channel', 32);
                $table->string('issue_type', 64)->nullable();
                $table->string('support_queue', 20)->nullable();
                $table->string('ticket_number')->unique();
                $table->string('subject');
                $table->text('message');
                $table->string('visitor_phone', 32)->nullable();
                $table->string('status')->default('open');
                $table->unsignedInteger('admin_unread_count')->default(0);
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_resolves_wallet_support_admin_when_phone_matches_wallet(): void
    {
        $wallet = WhatsappWallet::create(['phone_e164' => '2348012345678']);
        $account = ConsumerWalletApiAccount::create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => '2348012345678',
        ]);

        Admin::create([
            'name' => 'Support Agent',
            'email' => 'agent@example.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_WALLET_SUPPORT,
            'is_active' => true,
            'whatsapp_e164' => '2348012345678',
            'handles_wallet_support_in_app' => true,
        ]);

        $admin = app(WalletSupportStaffResolver::class)->resolveForAccount($account);

        $this->assertNotNull($admin);
        $this->assertTrue($admin->isWalletSupport());
    }

    public function test_does_not_resolve_when_in_app_handling_disabled(): void
    {
        $wallet = WhatsappWallet::create(['phone_e164' => '2348098765432']);
        $account = ConsumerWalletApiAccount::create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => '2348098765432',
        ]);

        Admin::create([
            'name' => 'Support Agent',
            'email' => 'agent2@example.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_WALLET_SUPPORT,
            'is_active' => true,
            'whatsapp_e164' => '2348098765432',
            'handles_wallet_support_in_app' => false,
        ]);

        $this->assertNull(app(WalletSupportStaffResolver::class)->resolveForAccount($account));
    }

    public function test_regular_customer_is_not_staff(): void
    {
        $wallet = WhatsappWallet::create(['phone_e164' => '2348011111111']);
        $account = ConsumerWalletApiAccount::create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => '2348011111111',
        ]);

        $this->assertFalse(app(WalletSupportStaffResolver::class)->isStaffAccount($account));
    }
}
