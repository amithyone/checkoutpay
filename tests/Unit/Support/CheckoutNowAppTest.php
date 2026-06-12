<?php

namespace Tests\Unit\Support;

use App\Support\CheckoutNowApp;
use Tests\TestCase;

class CheckoutNowAppTest extends TestCase
{
    public function test_android_apk_download_url_uses_same_origin_route_by_default(): void
    {
        config([
            'whatsapp.wallet_app_url' => 'https://app.check-outnow.com',
            'whatsapp.wallet_android_apk_url' => '',
            'app.url' => 'https://check-outnow.com',
        ]);

        $this->assertSame(
            'https://check-outnow.com/download/checkoutnow-android.apk',
            CheckoutNowApp::androidApkDownloadUrl()
        );
        $this->assertSame(CheckoutNowApp::androidApkDownloadUrl(), CheckoutNowApp::androidApkUrl());
    }

    public function test_android_apk_download_url_honours_override(): void
    {
        config([
            'whatsapp.wallet_android_apk_url' => 'https://cdn.example.com/checkoutnow.apk',
        ]);

        $this->assertSame('https://cdn.example.com/checkoutnow.apk', CheckoutNowApp::androidApkDownloadUrl());
    }
}
