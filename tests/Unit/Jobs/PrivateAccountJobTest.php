<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CreateBusinessPrivateAccountJob;
use App\Jobs\CreatePersonalPrivateAccountJob;
use App\Models\Business;
use App\Models\BusinessVerification;
use App\Models\WhatsappWallet;
use App\Services\MevonPay\MevonIdentityVerificationService;
use App\Services\MevonPay\MevonPrivateAccountService;
use App\Services\MevonPay\PrivateAccountProvisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PrivateAccountJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::connection('sqlite')->create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('business_id')->nullable()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('api_key')->nullable();
            $table->string('phone')->nullable();
            $table->string('cac_registration_number')->nullable();
            $table->date('rubies_signatory_dob')->nullable();
            $table->string('rubies_business_account_number')->nullable();
            $table->string('rubies_business_account_name')->nullable();
            $table->string('rubies_business_bank_name')->nullable();
            $table->string('rubies_business_bank_code')->nullable();
            $table->string('rubies_business_reference')->nullable();
            $table->timestamp('rubies_business_account_created_at')->nullable();
            $table->string('rubies_account_provision_status')->nullable();
            $table->text('rubies_account_provision_error')->nullable();
            $table->timestamp('rubies_account_provision_queued_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('sqlite')->create('business_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('verification_type');
            $table->string('status')->default('pending');
            $table->string('document_type')->nullable();
            $table->timestamp('provider_verified_at')->nullable();
            $table->string('provider_verify_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('sqlite')->dropIfExists('whatsapp_wallets');
        Schema::connection('sqlite')->create('whatsapp_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('phone_e164')->unique();
            $table->unsignedTinyInteger('tier')->default(1);
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('status')->default('active');
            $table->string('kyc_fname')->nullable();
            $table->string('kyc_lname')->nullable();
            $table->string('kyc_gender')->nullable();
            $table->date('kyc_dob')->nullable();
            $table->string('kyc_bvn')->nullable();
            $table->string('kyc_nin')->nullable();
            $table->string('kyc_email')->nullable();
            $table->string('rubies_account_type')->nullable();
            $table->string('mevon_virtual_account_number')->nullable();
            $table->string('mevon_account_name')->nullable();
            $table->string('mevon_bank_name')->nullable();
            $table->string('mevon_bank_code')->nullable();
            $table->string('mevon_reference')->nullable();
            $table->string('sender_name')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();
            $table->timestamp('tier2_provisioned_at')->nullable();
            $table->string('private_account_provision_status')->nullable();
            $table->text('private_account_provision_error')->nullable();
            $table->timestamp('private_account_provision_queued_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_business_job_persists_pay_in_account_on_success(): void
    {
        $business = Business::query()->create([
            'name' => 'Acme Ltd',
            'email' => 'acme-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'phone' => '08012345678',
            'cac_registration_number' => 'RC123456',
            'rubies_signatory_dob' => '1990-05-15',
        ]);

        BusinessVerification::query()->create([
            'business_id' => $business->id,
            'verification_type' => BusinessVerification::TYPE_BVN,
            'status' => BusinessVerification::STATUS_APPROVED,
            'document_type' => 'BVN: 12345678901',
            'provider_verify_status' => BusinessVerification::PROVIDER_VERIFY_PASSED,
            'provider_verified_at' => now(),
        ]);

        $mock = Mockery::mock(MevonPrivateAccountService::class);
        $mock->shouldReceive('createBusinessAccount')
            ->once()
            ->andReturn([
                'account_number' => '1000000001',
                'account_name' => 'Acme Ltd',
                'bank_name' => 'Rubies MFB',
                'bank_code' => '090175',
                'reference' => 'REF123',
                'raw' => [],
            ]);
        $this->app->instance(MevonPrivateAccountService::class, $mock);

        (new CreateBusinessPrivateAccountJob($business->id))->handle(
            $mock,
            app(PrivateAccountProvisionService::class),
        );

        $business->refresh();
        $this->assertSame('1000000001', $business->rubies_business_account_number);
        $this->assertSame(PrivateAccountProvisionService::STATUS_COMPLETED, $business->rubies_account_provision_status);
    }

    public function test_business_job_skips_when_account_already_exists(): void
    {
        $business = Business::query()->create([
            'name' => 'Existing Co',
            'email' => 'existing-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'rubies_business_account_number' => '9999999999',
        ]);

        $mock = Mockery::mock(MevonPrivateAccountService::class);
        $mock->shouldNotReceive('createBusinessAccount');
        $this->app->instance(MevonPrivateAccountService::class, $mock);

        (new CreateBusinessPrivateAccountJob($business->id))->handle(
            $mock,
            app(PrivateAccountProvisionService::class),
        );

        $business->refresh();
        $this->assertSame('9999999999', $business->rubies_business_account_number);
    }

    public function test_personal_job_persists_wallet_tier2_account_on_success(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345678',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'kyc_fname' => 'Ada',
            'kyc_lname' => 'Lovelace',
            'kyc_dob' => '1990-05-15',
            'kyc_email' => 'ada@example.com',
            'kyc_bvn' => '12345678901',
            'kyc_gender' => 'female',
        ]);

        $mock = Mockery::mock(MevonPrivateAccountService::class);
        $mock->shouldReceive('createPersonalAccount')
            ->once()
            ->andReturn([
                'account_number' => '2000000002',
                'account_name' => 'Ada Lovelace',
                'bank_name' => 'Rubies MFB',
                'bank_code' => '090175',
                'reference' => 'REF456',
                'raw' => [],
            ]);
        $this->app->instance(MevonPrivateAccountService::class, $mock);

        $identityMock = Mockery::mock(MevonIdentityVerificationService::class);
        $identityMock->shouldReceive('isBvnConfigured')->andReturn(true);
        $identityMock->shouldReceive('isNinConfigured')->andReturn(true);
        $identityMock->shouldReceive('verifyPersonal')
            ->once()
            ->andReturn(['ok' => true, 'message' => 'Identity verified via Mevon.', 'full_name' => 'Ada Lovelace']);
        $this->app->instance(MevonIdentityVerificationService::class, $identityMock);

        (new CreatePersonalPrivateAccountJob($wallet->id))->handle($mock, $identityMock);

        $wallet->refresh();
        $this->assertSame('2000000002', $wallet->mevon_virtual_account_number);
        $this->assertSame(WhatsappWallet::TIER_RUBIES_VA, $wallet->tier);
        $this->assertSame(PrivateAccountProvisionService::STATUS_COMPLETED, $wallet->private_account_provision_status);
    }

    public function test_dispatch_personal_sets_tier2_before_job_completes(): void
    {
        Queue::fake();

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348098765432',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        $mock = Mockery::mock(MevonPrivateAccountService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $this->app->instance(MevonPrivateAccountService::class, $mock);

        $result = app(PrivateAccountProvisionService::class)->dispatchPersonalIfReady($wallet, [
            'fname' => 'Chidi',
            'lname' => 'Okoro',
            'dob' => '1992-03-10',
            'email' => 'chidi@example.com',
            'gender' => 'male',
            'bvn' => '22334455667',
        ]);

        $this->assertTrue($result['dispatched']);
        $wallet->refresh();
        $this->assertSame(WhatsappWallet::TIER_RUBIES_VA, $wallet->tier);
        $this->assertSame(PrivateAccountProvisionService::STATUS_QUEUED, $wallet->private_account_provision_status);
        Queue::assertPushed(CreatePersonalPrivateAccountJob::class);
    }

    public function test_dispatch_from_stored_kyc_uses_wallet_fields_without_clearing_names(): void
    {
        Queue::fake();

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348011112222',
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'kyc_fname' => 'Ada',
            'kyc_lname' => 'Okonkwo',
            'kyc_dob' => '1991-08-20',
            'kyc_email' => 'ada@example.com',
            'kyc_bvn' => '11223344556',
            'kyc_gender' => 'female',
            'rubies_account_type' => 'personal',
        ]);

        $mock = Mockery::mock(MevonPrivateAccountService::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $this->app->instance(MevonPrivateAccountService::class, $mock);

        $result = app(PrivateAccountProvisionService::class)->dispatchPersonalFromStoredKyc($wallet);

        $this->assertTrue($result['dispatched']);
        $wallet->refresh();
        $this->assertSame('Ada', $wallet->kyc_fname);
        $this->assertSame('Okonkwo', $wallet->kyc_lname);
        Queue::assertPushed(CreatePersonalPrivateAccountJob::class);
    }

    public function test_personal_job_permanent_failure_resets_tier_for_app_retry(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348076543210',
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'kyc_fname' => 'Ngozi',
            'kyc_lname' => 'Eze',
            'kyc_dob' => '1988-11-22',
            'kyc_email' => 'ngozi@example.com',
            'kyc_bvn' => '99887766554',
            'kyc_gender' => 'female',
            'kyc_verified_at' => now(),
            'private_account_provision_status' => PrivateAccountProvisionService::STATUS_PROCESSING,
        ]);

        (new CreatePersonalPrivateAccountJob($wallet->id))->failed(new \RuntimeException('Mevon unavailable'));

        $wallet->refresh();
        $this->assertSame(WhatsappWallet::TIER_WHATSAPP_ONLY, $wallet->tier);
        $this->assertNull($wallet->kyc_verified_at);
        $this->assertSame(PrivateAccountProvisionService::STATUS_FAILED, $wallet->private_account_provision_status);
    }
}
