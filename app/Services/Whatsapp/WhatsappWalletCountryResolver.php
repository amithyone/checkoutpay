<?php

namespace App\Services\Whatsapp;

/**
 * Maps WhatsApp wallet phone E.164 and Evolution instance names to ISO currency codes.
 */
final class WhatsappWalletCountryResolver
{
    /**
     * Nigeria-only rails: virtual accounts, bank pay-in, Mavon bank payout, VTU, Tier 2 upgrade.
     */
    public function isNigeriaPayInWallet(string $phoneE164): bool
    {
        return $this->currencyForPhoneE164($phoneE164) === 'NGN';
    }

    public function currencyForPhoneE164(string $phoneE164): string
    {
        $d = preg_replace('/\D+/', '', $phoneE164) ?? '';
        if ($d === '') {
            return $this->fallbackCurrency();
        }

        $rows = WhatsappWalletRegionConfig::countryByDial();
        if (! is_array($rows) || $rows === []) {
            return $this->fallbackCurrency();
        }

        usort($rows, static fn ($a, $b): int => strlen((string) ($b['dial'] ?? '')) <=> strlen((string) ($a['dial'] ?? '')));

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $dial = (string) ($row['dial'] ?? '');
            if ($dial === '' || ! str_starts_with($d, $dial)) {
                continue;
            }
            if ($dial === '1') {
                return $this->currencyForNanpDigits($d);
            }

            $cur = (string) ($row['currency'] ?? '');
            if ($cur !== '') {
                return strtoupper($cur);
            }
        }

        return $this->fallbackCurrency();
    }

    public function currencyForEvolutionInstance(string $instance): string
    {
        $instances = WhatsappWalletRegionConfig::instances();
        if (is_array($instances) && isset($instances[$instance]) && is_array($instances[$instance])) {
            $cur = (string) ($instances[$instance]['currency'] ?? '');
            if ($cur !== '') {
                return strtoupper($cur);
            }
        }

        $cc = strtoupper((string) config('whatsapp_wallet_regions.unknown_instance_country', 'NG'));

        return $this->currencyForCountryIso($cc);
    }

    public function currencyForCountryIso(string $countryIso): string
    {
        $iso = strtoupper($countryIso);
        $rows = WhatsappWalletRegionConfig::countryByDial();
        if (! is_array($rows) || $rows === []) {
            return $this->fallbackCurrency();
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (strtoupper((string) ($row['country'] ?? '')) === $iso) {
                $cur = (string) ($row['currency'] ?? '');
                if ($cur !== '') {
                    return strtoupper($cur);
                }
            }
        }

        return $this->fallbackCurrency();
    }

    private function currencyForNanpDigits(string $digitsStartingWith1): string
    {
        if (strlen($digitsStartingWith1) < 4) {
            return 'USD';
        }
        $npa = (int) substr($digitsStartingWith1, 1, 3);
        $ca = config('whatsapp_wallet_regions.nanp_canadian_npa', []);
        if (is_array($ca) && in_array($npa, $ca, true)) {
            return 'CAD';
        }

        return 'USD';
    }

    private function fallbackCurrency(): string
    {
        $cc = strtoupper((string) config('whatsapp_wallet_regions.unknown_instance_country', 'NG'));

        return $this->currencyForCountryIso($cc);
    }
}
