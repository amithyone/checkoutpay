<?php

namespace Tests\Unit\Push;

use App\Services\Push\PushTokenDeliveryClassifier;
use Tests\TestCase;

class PushTokenDeliveryClassifierTest extends TestCase
{
    public function test_ios_platform_uses_apns(): void
    {
        $this->assertTrue(
            PushTokenDeliveryClassifier::shouldDeliverViaApns('ios', 'abc123def456')
        );
    }

    public function test_android_platform_uses_fcm(): void
    {
        $this->assertFalse(
            PushTokenDeliveryClassifier::shouldDeliverViaApns('android', 'dXyz:APA91bLongTokenExample')
        );
    }

    public function test_hex_token_without_platform_is_apns(): void
    {
        $token = str_repeat('a', 64);

        $this->assertTrue(PushTokenDeliveryClassifier::looksLikeApnsDeviceToken($token));
        $this->assertTrue(PushTokenDeliveryClassifier::shouldDeliverViaApns(null, $token));
    }

    public function test_fcm_token_shape_is_not_apns(): void
    {
        $token = 'dXyzExampleToken:APA91bH1234567890abcdefghijklmnopqrstuvwxyz';

        $this->assertTrue(PushTokenDeliveryClassifier::looksLikeFcmRegistrationToken($token));
        $this->assertFalse(PushTokenDeliveryClassifier::shouldDeliverViaApns(null, $token));
    }
}
