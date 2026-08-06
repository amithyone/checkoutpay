<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\TouchConsumerAppSession;
use App\Models\Business;
use App\Models\ConsumerWalletApiAccount;
use App\Models\CreditFacilityRequest;
use App\Models\WhatsappWallet;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerCreditFacilityRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(TouchConsumerAppSession::class);
        $this->ensureSchema();
    }

    public function test_overdraft_request_creates_pending_facility_and_business_application(): void
    {
        [$account, $business] = $this->seedLinkedBusinessWallet();

        Sanctum::actingAs($account, ['consumer']);

        $response = $this->postJson('/api/v1/consumer/wallet/credit-facility/request', [
            'kind' => 'overdraft',
            'amount' => 5000,
            'note' => 'Need float for stock',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Overdraft request submitted.')
            ->assertJsonPath('data.kind', 'overdraft')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', 5000)
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.note', 'Need float for stock')
            ->assertJsonPath('data.request.kind', 'overdraft');

        $this->assertDatabaseHas('credit_facility_requests', [
            'kind' => 'overdraft',
            'status' => 'pending',
            'business_id' => $business->id,
        ]);

        $business->refresh();
        $this->assertSame('pending', $business->overdraft_status);
        $this->assertSame(5000.0, (float) $business->overdraft_requested_amount);
        $this->assertSame('Need float for stock', $business->overdraft_application_notes);
    }

    public function test_accepts_data_request_wrapper(): void
    {
        [$account] = $this->seedLinkedBusinessWallet('2348099990002', 'Biz Two');

        Sanctum::actingAs($account, ['consumer']);

        $this->postJson('/api/v1/consumer/wallet/credit-facility/request', [
            'data' => [
                'request' => [
                    'kind' => 'loan',
                    'amount' => 12000,
                    'note' => null,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kind', 'loan')
            ->assertJsonPath('data.amount', 12000);

        $this->assertSame(1, CreditFacilityRequest::query()->where('kind', 'loan')->count());
    }

    public function test_rejects_duplicate_pending_overdraft(): void
    {
        [$account] = $this->seedLinkedBusinessWallet('2348099990003', 'Biz Three');

        Sanctum::actingAs($account, ['consumer']);

        $this->postJson('/api/v1/consumer/wallet/credit-facility/request', [
            'kind' => 'overdraft',
            'amount' => 2000,
        ])->assertOk();

        $this->postJson('/api/v1/consumer/wallet/credit-facility/request', [
            'kind' => 'overdraft',
            'amount' => 3000,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * @return array{0: ConsumerWalletApiAccount, 1: Business, 2: WhatsappWallet}
     */
    private function seedLinkedBusinessWallet(string $phone = '2348099990001', string $bizName = 'Biz One'): array
    {
        $business = Business::query()->create([
            'name' => $bizName,
            'email' => $phone.'@example.test',
            'password' => Hash::make('password'),
            'is_active' => true,
            'balance' => 0,
            'overdraft_eligible' => true,
            'overdraft_status' => null,
            'overdraft_limit' => 0,
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => $phone,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'balance' => 0,
            'linked_business_id' => $business->id,
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $phone,
        ]);

        return [$account, $business, $wallet];
    }

    private function ensureSchema(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');

        if (! $schema->hasTable('businesses')) {
            $schema->create('businesses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->boolean('is_active')->default(true);
                $table->decimal('balance', 15, 2)->default(0);
                $table->string('api_key')->nullable();
                $table->string('business_id', 32)->nullable();
                $table->decimal('overdraft_limit', 15, 2)->default(0);
                $table->timestamp('overdraft_approved_at')->nullable();
                $table->unsignedBigInteger('overdraft_approved_by')->nullable();
                $table->timestamp('overdraft_interest_last_charged_at')->nullable();
                $table->string('overdraft_status', 20)->nullable();
                $table->timestamp('overdraft_requested_at')->nullable();
                $table->decimal('overdraft_requested_amount', 15, 2)->nullable();
                $table->boolean('overdraft_eligible')->default(false);
                $table->decimal('overdraft_volume_90d', 15, 2)->default(0);
                $table->string('overdraft_volume_tier', 20)->nullable();
                $table->timestamp('overdraft_volume_computed_at')->nullable();
                $table->string('overdraft_repayment_mode', 20)->nullable();
                $table->text('overdraft_application_notes')->nullable();
                $table->string('overdraft_funding_source', 32)->nullable();
                $table->text('overdraft_approval_notes')->nullable();
                $table->timestamp('overdraft_repayment_started_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! $schema->hasTable('whatsapp_wallets')) {
            $schema->create('whatsapp_wallets', function (Blueprint $table) {
                $table->id();
                $table->string('phone_e164', 32)->unique();
                $table->string('status', 32)->default('active');
                $table->decimal('balance', 14, 2)->default(0);
                $table->unsignedBigInteger('linked_business_id')->nullable();
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

        if (! $schema->hasTable('personal_access_tokens')) {
            $schema->create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('credit_facility_requests')) {
            $schema->create('credit_facility_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->unsignedBigInteger('business_id')->nullable();
                $table->string('kind', 20);
                $table->decimal('amount', 15, 2);
                $table->string('currency', 3)->default('NGN');
                $table->text('note')->nullable();
                $table->string('status', 20)->default('pending');
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('settings')) {
            $schema->create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->nullable();
                $table->string('group')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('whatsapp_wallet_transactions')) {
            $schema->create('whatsapp_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->string('type');
                $table->string('ledger_scope')->nullable();
                $table->decimal('amount', 14, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('withdrawal_requests')) {
            $schema->create('withdrawal_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->string('status');
                $table->decimal('amount', 15, 2)->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }
}
