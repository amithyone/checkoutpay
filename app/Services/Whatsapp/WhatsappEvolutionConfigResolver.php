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
