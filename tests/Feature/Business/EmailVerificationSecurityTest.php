<?php

namespace Tests\Feature\Business;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsigned_verification_link_is_rejected(): void
    {
        $business = Business::create([
            'name' => 'Verify Biz',
            'email' => 'verify-unsigned@test.com',
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $response = $this->get('/dashboard/email/verify/'.$business->id.'/'.sha1($business->email));

        $response->assertStatus(403);
        $this->assertGuest('business');
        $this->assertNull($business->fresh()->email_verified_at);
    }

    public function test_signed_verification_marks_email_but_does_not_auto_login(): void
    {
        $business = Business::create([
            'name' => 'Verify Biz 2',
            'email' => 'verify-signed@test.com',
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $url = URL::temporarySignedRoute(
            'business.verification.verify',
            now()->addMinutes(60),
            ['id' => $business->id, 'hash' => sha1($business->email)]
        );

        $response = $this->get($url);

        $response->assertRedirect(route('business.login'));
        $this->assertGuest('business');
        $this->assertNotNull($business->fresh()->email_verified_at);
    }
}
