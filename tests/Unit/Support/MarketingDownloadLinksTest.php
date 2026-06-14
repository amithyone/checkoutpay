<?php

namespace Tests\Unit\Support;

use App\Models\Setting;
use App\Support\CheckoutNowApp;
use App\Support\CheckoutPayWordPressPlugin;
use App\Support\MarketingDownloadLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingDownloadLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_wordpress_plugin_url_defaults_to_wordpress_org(): void
    {
        config(['checkout.wordpress_plugin.wordpress_org_url' => '']);

        $this->assertSame(
            MarketingDownloadLinks::WORDPRESS_PLUGIN,
            MarketingDownloadLinks::wordpressPluginUrl()
        );
        $this->assertSame(
            MarketingDownloadLinks::WORDPRESS_PLUGIN,
            CheckoutPayWordPressPlugin::downloadUrl()
        );
    }

    public function test_play_store_url_defaults_to_google_play_listing(): void
    {
        config(['whatsapp.checkoutnow_play_store_url' => '']);

        $this->assertSame(
            MarketingDownloadLinks::PLAY_STORE,
            CheckoutNowApp::playStoreUrl()
        );
    }

    public function test_admin_setting_overrides_config(): void
    {
        Setting::set('wordpress_plugin_download_url', 'https://example.com/custom-plugin', 'string', 'marketing');

        $this->assertSame('https://example.com/custom-plugin', MarketingDownloadLinks::wordpressPluginUrl());
    }
}
