<?php

namespace App\Support;

use App\Models\Setting;

final class SiteBranding
{
    public static function name(): string
    {
        $fromSettings = Setting::get('site_name');

        if (is_string($fromSettings) && trim($fromSettings) !== '') {
            return trim($fromSettings);
        }

        return (string) config('app.name', 'CheckoutPay');
    }
}
