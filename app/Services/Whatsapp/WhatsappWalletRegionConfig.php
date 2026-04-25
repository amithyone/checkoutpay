<?php

namespace App\Services\Whatsapp;

use App\Models\Setting;

/**
 * Merges `config/whatsapp_wallet_regions.php` with admin-managed JSON in settings.
 */
final class WhatsappWalletRegionConfig
{
    /**
     * @return list<array{dial: string, country: string, currency: string, label: string}>
     */
    public static function countryByDial(): array
    {
        $base = config('whatsapp_wallet_regions.country_by_dial', []);
        if (! is_array($base)) {
            $base = [];
        }

        $byDial = [];
        foreach ($base as $row) {
            if (is_array($row) && isset($row['dial']) && (string) $row['dial'] !== '') {
                $d = (string) $row['dial'];
                $byDial[$d] = $row;
            }
        }

        $extra = Setting::get('whatsapp_country_dial_extra', []);
        if (! is_array($extra) || $extra === []) {
            return array_values($byDial);
        }

        foreach ($extra as $row) {
            if (! is_array($row)) {
                continue;
            }
            $d = preg_replace('/\D+/', '', (string) ($row['dial'] ?? '')) ?? '';
            if ($d === '' || (string) ($row['country'] ?? '') === '') {
                continue;
            }
            $cc = strtoupper(substr(preg_replace('/\s+/', '', (string) $row['country']), 0, 2));
            if (strlen($cc) !== 2) {
                continue;
            }
            $curRaw = strtoupper(preg_replace('/\s+/', '', (string) ($row['currency'] ?? 'USD')));
            $cur = strlen($curRaw) === 3 ? $curRaw : 'USD';
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                $label = $cc;
            }
            $byDial[$d] = [
                'dial' => $d,
                'country' => $cc,
                'currency' => $cur,
                'label' => $label,
            ];
        }

        return array_values($byDial);
    }

    /**
     * @return array<string, array{country: string, currency: string, label: string, features: array<string, bool>}>
     */
    public static function instances(): array
    {
        $base = config('whatsapp_wallet_regions.instances', []);
        if (! is_array($base)) {
            $base = [];
        }

        $extra = Setting::get('whatsapp_wallet_instances_extra', []);
        if (! is_array($extra) || $extra === []) {
            return $base;
        }

        return array_merge($base, $extra);
    }
}
