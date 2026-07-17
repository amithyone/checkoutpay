<?php

namespace App\Services\Region;

use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use App\Services\Whatsapp\WhatsappWalletRegionConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Country / currency / feature flags for consumer + business app routing.
 */
final class RegionCapabilitiesService
{
    public function __construct(
        protected WhatsappWalletCountryResolver $countries
    ) {}

    /**
     * @return array{
     *   country: string,
     *   currency: string,
     *   platform: string,
     *   label: string,
     *   rails: array{primary: string, dedicated_partner: string|null},
     *   features: array<string, bool|string>
     * }
     */
    public function forPhone(?string $phoneInput): array
    {
        $e164 = $phoneInput
            ? (PhoneNormalizer::canonicalAuthE164Digits($phoneInput) ?? PhoneNormalizer::digitsOnly($phoneInput))
            : null;

        $country = $e164
            ? $this->countries->countryIsoForPhoneE164($e164)
            : strtoupper((string) config('whatsapp_wallet_regions.unknown_instance_country', 'NG'));

        return $this->forCountryIso($country);
    }

    /**
     * @return array{
     *   country: string,
     *   currency: string,
     *   platform: string,
     *   label: string,
     *   rails: array{primary: string, dedicated_partner: string|null},
     *   features: array<string, bool|string>
     * }
     */
    public function forCountryIso(string $countryIso): array
    {
        $iso = strtoupper(trim($countryIso));
        if (strlen($iso) !== 2) {
            $iso = 'NG';
        }

        $currency = $this->countries->currencyForCountryIso($iso);
        $label = $this->labelForCountry($iso);
        $ke = $this->kenyaCashwyreSnapshot();

        if ($iso === 'NG') {
            return [
                'country' => 'NG',
                'currency' => 'NGN',
                'platform' => 'nigeria_region',
                'label' => $label,
                'rails' => [
                    'primary' => 'mevonpay',
                    'dedicated_partner' => null,
                ],
                'features' => [
                    'p2p' => true,
                    'cross_border_p2p' => true,
                    'bank_payin_va' => true,
                    'bank_payout' => true,
                    'mpesa_payout' => false,
                    'mpesa_collection' => false,
                    'bills' => true,
                    'airtime' => true,
                    'vtu_ng' => true,
                    'usd_virtual_card' => true,
                ],
            ];
        }

        if ($iso === 'KE') {
            return [
                'country' => 'KE',
                'currency' => 'KES',
                'platform' => 'kenya_region',
                'label' => $label,
                'rails' => [
                    'primary' => 'cashwyre',
                    'dedicated_partner' => 'mga_planned',
                ],
                'features' => [
                    'p2p' => true,
                    'cross_border_p2p' => true,
                    'bank_payin_va' => false,
                    'bank_payout' => (bool) ($ke['bank_payout'] ?? false),
                    'mpesa_payout' => (bool) ($ke['mpesa_payout'] ?? false),
                    'mpesa_collection' => (bool) ($ke['mpesa_collection'] ?? false),
                    'bills' => (bool) ($ke['bills'] ?? false),
                    'airtime' => (bool) ($ke['airtime'] ?? false),
                    'vtu_ng' => false,
                    'usd_virtual_card' => true,
                ],
            ];
        }

        $instanceFeatures = $this->instanceFeaturesForCountry($iso);

        return [
            'country' => $iso,
            'currency' => $currency,
            'platform' => strtolower($iso).'_region',
            'label' => $label,
            'rails' => [
                'primary' => 'wallet_p2p',
                'dedicated_partner' => null,
            ],
            'features' => [
                'p2p' => (bool) ($instanceFeatures['p2p'] ?? true),
                'cross_border_p2p' => (bool) ($instanceFeatures['cross_border_p2p'] ?? true),
                'bank_payin_va' => false,
                'bank_payout' => (bool) ($instanceFeatures['bank'] ?? false),
                'mpesa_payout' => false,
                'mpesa_collection' => false,
                'bills' => false,
                'airtime' => false,
                'vtu_ng' => false,
                'usd_virtual_card' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function kenyaCashwyreSnapshot(): array
    {
        $cached = Cache::get('cashwyre_kenya_capabilities');
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        return (array) config('cashwyre_kenya_capabilities', []);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function storeKenyaCashwyreSnapshot(array $snapshot): void
    {
        Cache::forever('cashwyre_kenya_capabilities', $snapshot);
    }

    protected function labelForCountry(string $iso): string
    {
        foreach (WhatsappWalletRegionConfig::countryByDial() as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (strtoupper((string) ($row['country'] ?? '')) === $iso) {
                return (string) ($row['label'] ?? $iso);
            }
        }

        return $iso;
    }

    /**
     * @return array<string, mixed>
     */
    protected function instanceFeaturesForCountry(string $iso): array
    {
        $instances = WhatsappWalletRegionConfig::instances();
        if (! is_array($instances)) {
            return [];
        }
        foreach ($instances as $cfg) {
            if (! is_array($cfg)) {
                continue;
            }
            if (strtoupper((string) ($cfg['country'] ?? '')) === $iso) {
                return is_array($cfg['features'] ?? null) ? $cfg['features'] : [];
            }
        }

        return ['p2p' => true, 'cross_border_p2p' => true];
    }
}
