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

    /**
     * Evolution instance for consumer OTP / outbound texts based on phone country.
     * Namibia uses admin setting `whatsapp_evolution_instance_namibia` (default "Namibia").
     */
    public static function walletInstanceForPhone(?string $phoneE164): string
    {
        $digits = PhoneNormalizer::digitsOnly($phoneE164 ?? '');
        if ($digits === null || $digits === '') {
            return self::walletInstance();
        }

        $country = app(WhatsappWalletCountryResolver::class)->countryIsoForPhoneE164($digits);

        if ($country === 'NA') {
            $db = Setting::get('whatsapp_evolution_instance_namibia');
            if (is_string($db) && trim($db) !== '') {
                return trim($db);
            }

            $fromMap = self::instanceNameForCountry('NA');
            if ($fromMap !== null) {
                return $fromMap;
            }

            $env = trim((string) env('WHATSAPP_EVOLUTION_INSTANCE_NAMIBIA', 'Namibia'));

            return $env !== '' ? $env : self::walletInstance();
        }

        $mapped = self::instanceNameForCountry($country);
        if ($mapped !== null && $country !== 'NG') {
            return $mapped;
        }

        return self::walletInstance();
    }

    /** @return string|null Evolution instance name configured for ISO country */
    private static function instanceNameForCountry(string $countryIso): ?string
    {
        $iso = strtoupper(trim($countryIso));
        foreach (WhatsappWalletRegionConfig::instances() as $name => $meta) {
            if (! is_array($meta)) {
                continue;
            }
            if (strtoupper((string) ($meta['country'] ?? '')) === $iso) {
                $name = trim((string) $name);

                return $name !== '' ? $name : null;
            }
        }

        return null;
    }

    public static function isRentalsOnlyInstance(string $instance): bool
    {
        $instance = trim($instance);
        if ($instance === '') {
            return false;
        }

        if (! (bool) config('whatsapp.evolution.rentals_dedicated_only', true)) {
            return false;
        }

        $rentals = self::rentalsInstance();
        if ($rentals === '' || strcasecmp($instance, $rentals) !== 0) {
            return false;
        }

        // Shared number: wallet OTP/bot and rentals on the same Evolution instance.
        if (strcasecmp(self::walletInstance(), $rentals) === 0
            || strcasecmp(self::defaultInstance(), $rentals) === 0) {
            return false;
        }

        return true;
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
