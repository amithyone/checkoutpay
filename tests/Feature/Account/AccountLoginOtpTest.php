<?php

namespace Tests\Feature\Account;

use App\Models\Business;
use App\Models\Renter;
use App\Services\Account\AccountLoginResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountLoginOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    /** @test */
    public function send_otp_resolves_business_account_when_users_table_is_missing(): void
    {
        $this->assertFalse(Schema::hasTable('users'));

        $business = Business::create([
            'name' => 'OTP Biz',
            'email' => 'Merchant@Example.com',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $response = $this->post('/my-account/login/send-otp', [
            'email' => 'merchant@example.com',
        ]);

        $response->assertRedirect(route('account.login.verify-otp'));
        $response->assertSessionHas('otp_email', 'merchant@example.com');

        $cached = Cache::get('login_otp:merchant@example.com');
        $this->assertNotNull($cached);
        $this->assertSame('business', $cached['guard']);
        $this->assertSame($business->id, $cached['id']);
    }

    /** @test */
    public function send_otp_resolves_renter_when_no_business_matches(): void
    {
        $renter = Renter::create([
            'name' => 'Renter One',
            'email' => 'renter@example.com',
            'password' => bcrypt('secret-password'),
        ]);

        $response = $this->post('/my-account/login/send-otp', [
            'email' => 'renter@example.com',
        ]);

        $response->assertRedirect(route('account.login.verify-otp'));

        $cached = Cache::get('login_otp:renter@example.com');
        $this->assertSame('renter', $cached['guard']);
        $this->assertSame($renter->id, $cached['id']);
    }

    /** @test */
    public function verify_otp_logs_business_account_into_business_dashboard(): void
    {
        $business = Business::create([
            'name' => 'Verify Biz',
            'email' => 'verifybiz@example.com',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Cache::put('login_otp:verifybiz@example.com', [
            'code' => '123456',
            'guard' => 'business',
            'id' => $business->id,
        ], now()->addMinutes(15));

        $response = $this->post('/my-account/login/verify-otp', [
            'email' => 'verifybiz@example.com',
            'code' => '123456',
        ]);

        $response->assertRedirect(route('business.dashboard'));
        $this->assertAuthenticatedAs($business, 'business');
    }

    /** @test */
    public function resolver_prefers_user_over_business_when_both_exist(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->unsignedBigInteger('business_id')->nullable();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        $business = Business::create([
            'name' => 'Dual Biz',
            'email' => 'dual@example.com',
            'password' => bcrypt('secret-password'),
            'is_active' => true,
        ]);

        $userId = \App\Models\User::create([
            'name' => 'Dual User',
            'email' => 'dual@example.com',
            'password' => bcrypt('secret-password'),
            'business_id' => $business->id,
        ])->id;

        $resolved = AccountLoginResolver::resolveByEmail('dual@example.com');

        $this->assertSame('web', $resolved['guard']);
        $this->assertSame($userId, $resolved['id']);
    }
}
