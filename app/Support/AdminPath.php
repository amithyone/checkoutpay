<?php

namespace App\Support;

final class AdminPath
{
    public static function prefix(): string
    {
        $path = trim((string) config('admin.path', 'enter0'), '/');

        return $path !== '' ? $path : 'enter0';
    }

    public static function honeypotPrefix(): string
    {
        $path = trim((string) config('admin.honeypot_path', 'admin'), '/');

        return $path !== '' ? $path : 'admin';
    }

    /** Absolute URL path prefix, e.g. /enter0 */
    public static function urlPrefix(): string
    {
        return '/'.self::prefix();
    }

    public static function requestIsAdminPanel(\Illuminate\Http\Request $request): bool
    {
        $prefix = self::prefix();

        return $request->is($prefix) || $request->is($prefix.'/*');
    }

    public static function requestIsHoneypot(\Illuminate\Http\Request $request): bool
    {
        $prefix = self::honeypotPrefix();

        return $request->is($prefix) || $request->is($prefix.'/*');
    }
}
