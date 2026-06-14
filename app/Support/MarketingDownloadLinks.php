<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-configurable marketing download URLs (WordPress plugin, app stores).
 */
final class MarketingDownloadLinks
{
    public const WORDPRESS_PLUGIN = 'https://wordpress.org/plugins/copn-payment-gateway';

    public const PLAY_STORE = 'https://play.google.com/store/apps/details?id=com.checkoutnow.app';

    public static function wordpressPluginUrl(): string
    {
        return self::resolve(
            'wordpress_plugin_download_url',
            'checkout.wordpress_plugin.wordpress_org_url',
            self::WORDPRESS_PLUGIN
        );
    }

    public static function checkoutNowPlayStoreUrl(): string
    {
        return self::resolve(
            'checkoutnow_play_store_url',
            'whatsapp.checkoutnow_play_store_url',
            self::PLAY_STORE
        );
    }

    public static function checkoutNowAppStoreUrl(): ?string
    {
        return self::resolveNullable(
            'checkoutnow_app_store_url',
            'whatsapp.checkoutnow_app_store_url'
        );
    }

    public static function hasAdminWordPressPluginUrl(): bool
    {
        return self::hasAdminOverride('wordpress_plugin_download_url');
    }

    public static function hasAdminPlayStoreUrl(): bool
    {
        return self::hasAdminOverride('checkoutnow_play_store_url');
    }

    public static function hasAdminAppStoreUrl(): bool
    {
        return self::hasAdminOverride('checkoutnow_app_store_url');
    }

    private static function resolve(string $settingKey, string $configKey, string $default): string
    {
        $fromSetting = Setting::get($settingKey);
        if (is_string($fromSetting) && trim($fromSetting) !== '') {
            return rtrim(trim($fromSetting), '/');
        }

        $fromConfig = rtrim((string) config($configKey, ''), '/');
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        return rtrim($default, '/');
    }

    private static function resolveNullable(string $settingKey, string $configKey): ?string
    {
        $fromSetting = Setting::get($settingKey);
        if (is_string($fromSetting) && trim($fromSetting) !== '') {
            return rtrim(trim($fromSetting), '/');
        }

        $fromConfig = rtrim((string) config($configKey, ''), '/');

        return $fromConfig !== '' ? $fromConfig : null;
    }

    private static function hasAdminOverride(string $settingKey): bool
    {
        $fromSetting = Setting::get($settingKey);

        return is_string($fromSetting) && trim($fromSetting) !== '';
    }
}
