<?php

namespace Tests\Feature\Business;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyRegenerateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function business_can_regenerate_api_key_from_keys_page(): void
    {
        $business = Business::create([
            'name' => 'Keys Biz',
            'email' => 'keysbiz@test.com',
            'is_active' => true,
        ]);

        $oldKey = $business->api_key;
        $this->assertNotEmpty($oldKey);

        $this->actingAs($business, 'business');

        $response = $this->from('/dashboard/keys')->post('/dashboard/keys/regenerate-api-key', []);

        $response->assertRedirect('/dashboard/keys');
        $response->assertSessionHas('success');

        $business->refresh();
        $this->assertNotSame($oldKey, $business->api_key);
        $this->assertStringStartsWith('pk_', $business->api_key);
    }

    /** @test */
    public function business_regenerate_from_settings_stays_on_settings(): void
    {
        $business = Business::create([
            'name' => 'Settings Biz',
            'email' => 'settingsbiz@test.com',
            'is_active' => true,
        ]);

        $this->actingAs($business, 'business');

        $response = $this->from('/dashboard/settings')->post('/dashboard/settings/regenerate-api-key', []);

        $response->assertRedirect('/dashboard/settings');
        $business->refresh();
        $this->assertNotEmpty($business->api_key);
    }
}
