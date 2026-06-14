<?php

namespace Tests\Unit\Support;

use App\Support\CheckoutNowApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutNowAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_android_apk_download_url_uses_same_origin_route_by_default(): void
    {
        config([
            'whatsapp.wallet_app_url' => 'https://app.check-outnow.com',
            'whatsapp.wallet_android_apk_url' => '',
        ]);

        $this->assertStringEndsWith(
            '/download/checkoutnow-android.apk',
            CheckoutNowApp::androidApkDownloadUrl()
        );
        $this->assertSame(CheckoutNowApp::androidApkDownloadUrl(), CheckoutNowApp::androidApkUrl());
    }

    public function test_play_store_url_honours_config_override(): void
    {
        config([
            'whatsapp.checkoutnow_play_store_url' => 'https://play.google.com/store/apps/details?id=custom.app',
        ]);

        $this->assertSame(
            'https://play.google.com/store/apps/details?id=custom.app',
            CheckoutNowApp::playStoreUrl()
        );
    }
}
