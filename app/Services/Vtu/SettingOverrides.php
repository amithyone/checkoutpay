<?php

namespace App\Services\Vtu;

use App\Models\Setting;

final class SettingOverrides
{
    public static function vtuNgEnabledOverride(): ?bool
    {
        $v = Setting::get('vtu_ng_enabled');

        return $v === null ? null : (bool) $v;
    }

    public static function mevonPayVtuEnabledOverride(): ?bool
    {
        $v = Setting::get('mevonpay_vtu_enabled');

        return $v === null ? null : (bool) $v;
    }

    public static function squadVtuEnabledOverride(): ?bool
    {
        $v = Setting::get('squad_vtu_enabled');

        return $v === null ? null : (bool) $v;
    }
}
