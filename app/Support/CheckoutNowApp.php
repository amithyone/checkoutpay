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

    public static function androidApkUrl(): string
    {
        $configured = rtrim((string) config('whatsapp.wallet_android_apk_url', ''), '/');
        if ($configured !== '') {
            return $configured;
        }

        return self::webUrl().'/checkoutnow-android.apk';
    }

    public static function brandName(): string
    {
        return 'CheckoutNow';
    }
}
