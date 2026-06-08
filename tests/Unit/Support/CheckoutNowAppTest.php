<?php

namespace Tests\Unit\Support;

use App\Support\CheckoutNowApp;
use Tests\TestCase;

class CheckoutNowAppTest extends TestCase
{
    public function test_android_apk_url_defaults_to_app_host(): void
    {
        config([
            'whatsapp.wallet_app_url' => 'https://app.check-outnow.com',
            'whatsapp.wallet_android_apk_url' => '',
        ]);

        $this->assertSame(
            'https://app.check-outnow.com/checkoutnow-android.apk',
            CheckoutNowApp::androidApkUrl()
        );
    }

    public function test_android_apk_url_honours_override(): void
    {
        config([
            'whatsapp.wallet_android_apk_url' => 'https://cdn.example.com/checkoutnow.apk',
        ]);

        $this->assertSame('https://cdn.example.com/checkoutnow.apk', CheckoutNowApp::androidApkUrl());
    }
}
