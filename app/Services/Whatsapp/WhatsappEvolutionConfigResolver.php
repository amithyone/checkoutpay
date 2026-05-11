<?php

namespace App\Services\Whatsapp;

use App\Models\Setting;

/**
 * Evolution API URL/key/instance: admin Settings override .env (matches WhatsApp wallet admin form).
 */
final class WhatsappEvolutionConfigResolver
{
    public static function baseUrl(): string
    {
        $db = Setting::get('whatsapp_evolution_base_url');
        if (is_string($db) && trim($db) !== '') {
            return rtrim(trim($db), '/');
        }

        return rtrim((string) config('whatsapp.evolution.base_url', ''), '/');
    }

    public static function apiKey(): string
    {
        $db = Setting::get('whatsapp_evolution_api_key');
        if (is_string($db) && trim($db) !== '') {
            return trim($db);
        }

        return trim((string) config('whatsapp.evolution.api_key', ''));
    }

    public static function defaultInstance(): string
    {
        $db = Setting::get('whatsapp_evolution_instance_default');
        if (is_string($db) && trim($db) !== '') {
            return trim($db);
        }

        return trim((string) config('whatsapp.evolution.instance', ''));
    }

    public static function rentalsInstance(): string
    {
        $db = Setting::get('whatsapp_evolution_instance_rentals');
        if (is_string($db) && trim($db) !== '') {
            return trim($db);
        }

        return trim((string) config('whatsapp.evolution.rentals_instance', ''));
    }

    public static function walletInstance(): string
    {
        $db = Setting::get('whatsapp_evolution_instance_wallet');
        if (is_string($db) && trim($db) !== '') {
            return trim($db);
        }

        $cfg = trim((string) config('whatsapp.evolution.wallet_instance', ''));
        if ($cfg !== '') {
            return $cfg;
        }

        return self::defaultInstance();
    }

    public static function isRentalsOnlyInstance(string $instance): bool
    {
        $instance = trim($instance);
        if ($instance === '') {
            return false;
        }

        return strcasecmp($instance, self::rentalsInstance()) === 0;
    }

    /** Public Checkout base for webhook URL (admin whatsapp_app_url overrides WHATSAPP_APP_URL / APP_URL). */
    public static function publicAppBaseUrl(): string
    {
        $db = Setting::get('whatsapp_app_url');
        if (is_string($db) && trim($db) !== '') {
            return rtrim(trim($db), '/');
        }

        return rtrim((string) config('whatsapp.public_url', ''), '/');
    }
}
