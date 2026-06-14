<?php

namespace App\Services\Whatsapp;

use App\Models\Setting;

/**
 * Platform-wide country allowlist for Checkout WhatsApp Pay Code.
 */
final class WhatsappCheckoutPayCodePolicy
{
    private const SETTING_KEY = 'whatsapp_checkout_pay_code_enabled_countries';

    /**
     * @return list<string> ISO-3166 alpha-2 codes
     */
    public static function enabledCountries(): array
    {
        $stored = Setting::get(self::SETTING_KEY, null);
        if (is_array($stored) && $stored !== []) {
            return array_values(array_unique(array_filter(array_map(
                static fn ($cc) => strtoupper(substr(preg_replace('/\s+/', '', (string) $cc), 0, 2)),
                $stored
            ), static fn (string $cc): bool => strlen($cc) === 2)));
        }

        return ['NG'];
    }

    public static function isGloballyEnabled(): bool
    {
        return self::enabledCountries() !== [];
    }

    public static function customerCountryAllowed(string $phoneE164): bool
    {
        $iso = app(WhatsappWalletCountryResolver::class)->countryIsoForPhoneE164($phoneE164);

        return in_array(strtoupper($iso), self::enabledCountries(), true);
    }

    /**
     * @return list<array{country: string, label: string, currency: string}>
     */
    public static function countryOptionsForAdmin(): array
    {
        $seen = [];
        $options = [];

        foreach (WhatsappWalletRegionConfig::countryByDial() as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cc = strtoupper((string) ($row['country'] ?? ''));
            if (strlen($cc) !== 2 || isset($seen[$cc])) {
                continue;
            }
            $seen[$cc] = true;
            $options[] = [
                'country' => $cc,
                'label' => (string) ($row['label'] ?? $cc),
                'currency' => strtoupper((string) ($row['currency'] ?? '')),
            ];
        }

        foreach (WhatsappWalletRegionConfig::instances() as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cc = strtoupper((string) ($row['country'] ?? ''));
            if (strlen($cc) !== 2 || isset($seen[$cc])) {
                continue;
            }
            $seen[$cc] = true;
            $options[] = [
                'country' => $cc,
                'label' => (string) ($row['label'] ?? $cc),
                'currency' => strtoupper((string) ($row['currency'] ?? '')),
            ];
        }

        usort($options, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }
}
