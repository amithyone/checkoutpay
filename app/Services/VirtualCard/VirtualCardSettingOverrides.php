<?php

namespace App\Services\VirtualCard;

use App\Models\Setting;

final class VirtualCardSettingOverrides
{
    public static function mevonPayCardEnabledOverride(): ?bool
    {
        $v = Setting::get('mevonpay_card_enabled');

        return $v === null ? null : (bool) $v;
    }

    public static function cashwyreCardEnabledOverride(): ?bool
    {
        $v = Setting::get('cashwyre_card_enabled');

        return $v === null ? null : (bool) $v;
    }
}
