<?php

namespace App\Support;

/**
 * Public URLs for the CheckoutNow consumer wallet app (web + Android APK).
 */
final class CheckoutNowApp
{
    public static function webUrl(): string
    {
        $configured = rtrim((string) config('whatsapp.wallet_app_url', ''), '/');
        if ($configured !== '') {
            return $configured;
        }

        return 'https://app.check-outnow.com';
    }

    /**
     * Same-origin URL that forces a file download (products page, marketing links).
     */
    public static function androidApkDownloadUrl(): string
    {
        $configured = rtrim((string) config('whatsapp.wallet_android_apk_url', ''), '/');
        if ($configured !== '') {
            return $configured;
        }

        // Use url() so stale route:cache cannot break marketing pages after deploy.
        return url('/download/checkoutnow-android.apk');
    }

    /**
     * @deprecated Prefer androidApkDownloadUrl() for user-facing links.
     */
    public static function androidApkUrl(): string
    {
        return self::androidApkDownloadUrl();
    }

    public static function androidApkPath(): string
    {
        return (string) config('whatsapp.wallet_android_apk_path', '/var/www/checkoutnow/dist/checkoutnow-android.apk');
    }

    public static function brandName(): string
    {
        return 'CheckoutNow';
    }

    /**
     * Google Play Store listing for CheckoutNow (APK download until listing is live).
     */
    public static function playStoreUrl(): string
    {
        return MarketingDownloadLinks::checkoutNowPlayStoreUrl();
    }

    /**
     * Apple App Store listing for CheckoutNow (web app until listing is live).
     */
    public static function appStoreUrl(): string
    {
        $configured = MarketingDownloadLinks::checkoutNowAppStoreUrl();
        if ($configured !== null) {
            return $configured;
        }

        return self::webUrl();
    }

    public static function hasConfiguredPlayStoreUrl(): bool
    {
        return MarketingDownloadLinks::hasAdminPlayStoreUrl()
            || rtrim((string) config('whatsapp.checkoutnow_play_store_url', ''), '/') !== '';
    }

    public static function hasConfiguredAppStoreUrl(): bool
    {
        return MarketingDownloadLinks::checkoutNowAppStoreUrl() !== null;
    }
}
