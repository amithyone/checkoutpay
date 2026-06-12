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

        return route('checkoutnow.apk.download');
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
}
