<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticateApiKeyVerifiedEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_business_cannot_use_api_key(): void
    {
        $business = Business::create([
            'name' => 'Unverified API Biz',
            'email' => 'unverified-api@test.com',
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $response = $this->getJson('/api/v1/banks', [
            'X-API-Key' => $business->api_key,
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Email verification required before using the API']);
    }
}
