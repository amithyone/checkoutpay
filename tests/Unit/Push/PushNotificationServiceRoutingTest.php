<?php

namespace Tests\Unit\Push;

use App\Services\Push\ApnsPushNotificationService;
use App\Services\PushNotificationService;
use Mockery;
use Tests\TestCase;

class PushNotificationServiceRoutingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_checkoutnow_ios_token_routes_to_apns(): void
    {
        config([
            'services.firebase.checkoutnow.project_id' => '',
            'services.firebase.checkoutnow.service_account_json' => '',
            'services.apns.checkoutnow.key_id' => 'TESTKEYID1',
            'services.apns.checkoutnow.team_id' => 'TEAMID1234',
            'services.apns.checkoutnow.bundle_id' => 'com.checkoutnow.mobile',
            'services.apns.checkoutnow.private_key' => __FILE__,
        ]);

        $apns = Mockery::mock(ApnsPushNotificationService::class);
        $apns->shouldReceive('isConfigured')
            ->with(PushNotificationService::PROFILE_CHECKOUTNOW)
            ->andReturn(true);
        $apns->shouldReceive('sendToDevice')
            ->once()
            ->withArgs(function ($token, $title, $body, $data, $profile) {
                return $token === str_repeat('a', 64)
                    && $title === 'Hello'
                    && $body === 'World'
                    && ($data['type'] ?? '') === 'test'
                    && $profile === PushNotificationService::PROFILE_CHECKOUTNOW;
            })
            ->andReturn([]);

        $this->app->instance(ApnsPushNotificationService::class, $apns);

        $failed = $this->app->make(PushNotificationService::class)->sendToTokens(
            [['token' => str_repeat('a', 64), 'platform' => 'ios']],
            'Hello',
            'World',
            ['type' => 'test'],
            'money_received',
            PushNotificationService::PROFILE_CHECKOUTNOW,
        );

        $this->assertSame([], $failed);
    }
}
