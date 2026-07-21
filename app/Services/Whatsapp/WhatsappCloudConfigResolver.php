<?php

namespace App\Services\Whatsapp;

use App\Models\Setting;

/**
 * Meta WhatsApp Cloud API credentials and phone_number_id routing.
 * Admin DB settings (whatsapp_cloud_*) override .env when set.
 */
final class WhatsappCloudConfigResolver
{
    public static function isEnabled(): bool
    {
        return strtolower((string) config('whatsapp.provider', 'evolution')) === 'cloud';
    }

    public static function graphVersion(): string
    {
        return trim((string) config('whatsapp.cloud.graph_version', 'v21.0'), '/');
    }

    public static function accessToken(): string
    {
        $db = Setting::get('whatsapp_cloud_access_token');
        if (is_string($db) && trim($db) !== '') {
            return trim($db);
        }

        return trim((string) config('whatsapp.cloud.access_token', ''));
    }

    public static function appSecret(): string
    {
        $db = Setting::get('whatsapp_cloud_app_secret');
        if (is_string($db) && trim($db) !== '') {
            return trim($db);
        }

        return trim((string) config('whatsapp.cloud.app_secret', ''));
    }

    public static function verifyToken(): string
    {
        $db = Setting::get('whatsapp_cloud_verify_token');
        if (is_string($db) && trim($db) !== '') {
            return trim($db);
        }

        $configured = trim((string) config('whatsapp.cloud.verify_token', ''));
        if ($configured !== '') {
            return $configured;
        }

        // Legacy shared secret can double as verify token during migration.
        return trim((string) config('whatsapp.webhook_secret', ''));
    }

    public static function defaultPhoneNumberId(): string
    {
        $db = Setting::get('whatsapp_cloud_phone_number_id');
        if (is_string($db) && trim($db) !== '') {
            return trim($db);
        }

        return trim((string) config('whatsapp.cloud.phone_number_id', ''));
    }

    public static function walletPhoneNumberId(): string
    {
        $db = Setting::get('whatsapp_cloud_phone_number_id_wallet');
        if (is_string($db) && trim($db) !== '') {
            return trim($db);
        }

        $cfg = trim((string) config('whatsapp.cloud.phone_number_id_wallet', ''));
        if ($cfg !== '') {
            return $cfg;
        }

        return self::defaultPhoneNumberId();
    }

    public static function rentalsPhoneNumberId(): string
    {
        $db = Setting::get('whatsapp_cloud_phone_number_id_rentals');
        if (is_string($db) && trim($db) !== '') {
            return trim($db);
        }

        return trim((string) config('whatsapp.cloud.phone_number_id_rentals', ''));
    }

    /**
     * Map Evolution-style instance name → Meta phone_number_id for outbound sends.
     */
    public static function phoneNumberIdForInstance(string $instanceName): string
    {
        $instanceName = trim($instanceName);
        if ($instanceName === '') {
            return self::defaultPhoneNumberId();
        }

        $rentalsInstance = WhatsappEvolutionConfigResolver::rentalsInstance();
        if ($rentalsInstance !== '' && strcasecmp($instanceName, $rentalsInstance) === 0) {
            $rentalsId = self::rentalsPhoneNumberId();
            if ($rentalsId !== '') {
                return $rentalsId;
            }
        }

        $walletInstance = WhatsappEvolutionConfigResolver::walletInstance();
        if ($walletInstance !== '' && strcasecmp($instanceName, $walletInstance) === 0) {
            return self::walletPhoneNumberId();
        }

        return self::defaultPhoneNumberId();
    }

    /**
     * Map inbound webhook phone_number_id → Evolution-style instance for existing bot routing.
     */
    public static function instanceForPhoneNumberId(string $phoneNumberId): string
    {
        $phoneNumberId = trim($phoneNumberId);
        if ($phoneNumberId === '') {
            return WhatsappEvolutionConfigResolver::defaultInstance();
        }

        $map = [
            self::defaultPhoneNumberId() => WhatsappEvolutionConfigResolver::defaultInstance(),
            self::walletPhoneNumberId() => WhatsappEvolutionConfigResolver::walletInstance(),
            self::rentalsPhoneNumberId() => WhatsappEvolutionConfigResolver::rentalsInstance(),
        ];

        foreach ($map as $id => $instance) {
            if ($id !== '' && $id === $phoneNumberId && $instance !== '') {
                return $instance;
            }
        }

        return WhatsappEvolutionConfigResolver::defaultInstance() ?: 'cloud_default';
    }

    public static function graphBaseUrl(): string
    {
        return 'https://graph.facebook.com/'.self::graphVersion();
    }
}
