<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Business;
use App\Models\BusinessVerification;
use App\Services\Admin\AdminSidebarMenu;
use App\Services\Admin\BusinessKycMevonVerificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class BusinessKycAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::connection('sqlite')->create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->boolean('is_active')->default(true);
            $table->json('sidebar_menu_order')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('payment_status_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->timestamps();
        });

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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('sqlite')->create('business_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('verification_type');
            $table->string('status')->default('pending');
            $table->string('document_type')->nullable();
            $table->string('document_path')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('provider_verified_at')->nullable();
            $table->unsignedBigInteger('provider_verified_by')->nullable();
            $table->string('provider_verified_name')->nullable();
            $table->string('provider_verify_reference')->nullable();
            $table->string('provider_verify_status')->nullable();
            $table->text('provider_verify_message')->nullable();
            $table->json('provider_verify_payload')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $menu = Mockery::mock(AdminSidebarMenu::class);
        $menu->shouldReceive('itemsFor')->andReturn([]);
        $this->app->instance(AdminSidebarMenu::class, $menu);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'name' => 'KYC Admin',
            'email' => 'kyc-admin-'.uniqid().'@check-outpay.com',
            'password' => Hash::make('password'),
            'role' => Admin::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function business(array $overrides = []): Business
    {
        return Business::query()->create(array_merge([
            'name' => 'Test Merchant',
            'email' => 'merchant-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'rubies_signatory_dob' => '1990-05-15',
        ], $overrides));
    }

    public function test_guest_cannot_access_business_kyc_queue(): void
    {
        $this->get(route('admin.businesses-kyc.index'))
            ->assertRedirect();
    }

    public function test_admin_can_view_pending_verification_documents(): void
    {
        $admin = $this->admin();
        $business = $this->business();

        BusinessVerification::query()->create([
            'business_id' => $business->id,
            'verification_type' => BusinessVerification::TYPE_UTILITY_BILL,
            'status' => BusinessVerification::STATUS_PENDING,
            'document_type' => 'Electricity bill',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.businesses-kyc.index'))
            ->assertOk()
            ->assertSee('Verification document queue')
            ->assertSee('Utility Bill')
            ->assertSee('Test Merchant');
    }

    public function test_admin_can_preview_verification_document(): void
    {
        Storage::fake('public');

        $admin = $this->admin();
        $business = $this->business();
        $path = 'business-verifications/test-id.pdf';
        Storage::disk('public')->put($path, 'fake pdf content');

        $verification = BusinessVerification::query()->create([
            'business_id' => $business->id,
            'verification_type' => BusinessVerification::TYPE_CAC_CERTIFICATE,
            'status' => BusinessVerification::STATUS_PENDING,
            'document_type' => 'CAC Certificate',
            'document_path' => $path,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.businesses-kyc.document', $verification))
            ->assertOk();
    }

    public function test_admin_cannot_approve_bvn_without_mevon_verification(): void
    {
        $admin = $this->admin();
        $business = $this->business(['name' => 'Ada Lovelace']);

        $verification = BusinessVerification::query()->create([
            'business_id' => $business->id,
            'verification_type' => BusinessVerification::TYPE_BVN,
            'status' => BusinessVerification::STATUS_PENDING,
            'document_type' => 'BVN: 12345678901',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.businesses.verification.approve', [$business, $verification]), [
                'return_to' => 'kyc_queue',
            ])
            ->assertRedirect(route('admin.businesses-kyc.index', ['status' => 'pending']))
            ->assertSessionHas('warning');

        $verification->refresh();
        $this->assertSame(BusinessVerification::STATUS_PENDING, $verification->status);
    }

    public function test_admin_can_approve_bvn_after_mevon_verification_passes(): void
    {
        $admin = $this->admin();
        $business = $this->business(['name' => 'Ada Lovelace']);

        $verification = BusinessVerification::query()->create([
            'business_id' => $business->id,
            'verification_type' => BusinessVerification::TYPE_BVN,
            'status' => BusinessVerification::STATUS_PENDING,
            'document_type' => 'BVN: 12345678901',
            'provider_verified_at' => now(),
            'provider_verified_by' => $admin->id,
            'provider_verified_name' => 'Ada Lovelace',
            'provider_verify_status' => BusinessVerification::PROVIDER_VERIFY_PASSED,
            'provider_verify_message' => 'Mevon verified identity and name match.',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.businesses.verification.approve', [$business, $verification]), [
                'return_to' => 'kyc_queue',
                'admin_notes' => 'Looks good',
            ])
            ->assertRedirect(route('admin.businesses-kyc.index', ['status' => 'pending']));

        $verification->refresh();
        $this->assertSame(BusinessVerification::STATUS_APPROVED, $verification->status);
        $this->assertSame($admin->id, $verification->reviewed_by);
    }

    public function test_admin_can_run_mevon_verify_for_nin(): void
    {
        $admin = $this->admin();
        $business = $this->business(['name' => 'John Doe']);

        $verification = BusinessVerification::query()->create([
            'business_id' => $business->id,
            'verification_type' => BusinessVerification::TYPE_NIN,
            'status' => BusinessVerification::STATUS_PENDING,
            'document_type' => 'NIN: 98765432109',
        ]);

        $verified = $verification->fresh();
        $verified->provider_verify_status = BusinessVerification::PROVIDER_VERIFY_PASSED;
        $verified->provider_verified_name = 'John Doe';

        $mock = Mockery::mock(BusinessKycMevonVerificationService::class);
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('verify')
            ->once()
            ->with(Mockery::on(fn ($v) => $v->id === $verification->id), Mockery::on(fn ($a) => $a->id === $admin->id), null)
            ->andReturn([
                'ok' => true,
                'message' => 'Identity verified via Mevon. Registered name: John Doe',
                'verification' => $verified,
            ]);
        $this->app->instance(BusinessKycMevonVerificationService::class, $mock);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.businesses-kyc.verify-identity', $verification), [
                'return_to' => 'kyc_queue',
            ])
            ->assertRedirect(route('admin.businesses-kyc.index', ['status' => 'pending']))
            ->assertSessionHas('success');
    }
}
