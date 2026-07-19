<?php

namespace App\Services\Whatsapp;

use App\Models\Setting;

/**
 * Gate for unsolicited WhatsApp alerts (not OTP, not interactive bot replies).
 */
final class WhatsappProactiveOutbound
{
    public static function enabled(): bool
    {
        $stored = Setting::get('whatsapp_proactive_notifications_enabled');
        if ($stored !== null) {
            return (bool) $stored;
        }

        return (bool) config('whatsapp.proactive_notifications_enabled', false);
    }
}
