<?php

namespace App\Services\Support;

use App\Services\Whatsapp\WhatsappWalletRegionConfig;
use Illuminate\Http\Request;

final class SupportCountryOptionsService
{
    public function __construct(
        private SupportIssueOptionsService $issues,
    ) {}
    /**
     * @return list<array{iso: string, label: string, dial: string}>
     */
    public function supportedCountries(): array
    {
        $byIso = [];
        foreach (WhatsappWalletRegionConfig::countryByDial() as $row) {
            $iso = strtoupper((string) ($row['country'] ?? ''));
            if (strlen($iso) !== 2) {
                continue;
            }
            if (! isset($byIso[$iso])) {
                $byIso[$iso] = [
                    'iso' => $iso,
                    'label' => (string) ($row['label'] ?? $iso),
                    'dial' => (string) ($row['dial'] ?? ''),
                ];
            }
        }

        $countries = array_values($byIso);
        usort($countries, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return $countries;
    }

    /**
     * @return array{countries: list<array{iso: string, label: string, dial: string}>, suggested_country: string, default_country: string}
     */
    public function optionsForRequest(Request $request): array
    {
        $countries = $this->supportedCountries();
        $default = strtoupper((string) config('support.default_country', 'NG'));
        $supportedIsos = array_column($countries, 'iso');

        $guessed = $this->guessCountryFromRequest($request);
        $suggested = in_array($guessed, $supportedIsos, true) ? $guessed : $default;
        if (! in_array($suggested, $supportedIsos, true)) {
            $suggested = $countries[0]['iso'] ?? 'NG';
        }

        return [
            'countries' => $countries,
            'suggested_country' => $suggested,
            'default_country' => in_array($default, $supportedIsos, true) ? $default : $suggested,
            'issue_types' => $this->issues->issueTypes(),
            'payment_session_label' => 'Bank session ID',
            'payment_session_hint' => 'On your bank app transfer receipt or SMS — not the checkout website URL',
        ];
    }

    public function isSupportedCountry(string $iso): bool
    {
        $iso = strtoupper(substr(trim($iso), 0, 2));

        foreach ($this->supportedCountries() as $row) {
            if ($row['iso'] === $iso) {
                return true;
            }
        }

        return false;
    }

    private function guessCountryFromRequest(Request $request): string
    {
        $headers = [
            $request->header('CF-IPCountry'),
            $request->header('X-Country-Code'),
            $request->header('X-AppEngine-Country'),
        ];

        foreach ($headers as $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            $cc = strtoupper(substr(trim($value), 0, 2));
            if ($cc !== 'XX' && $cc !== 'T1' && strlen($cc) === 2) {
                return $cc;
            }
        }

        return strtoupper((string) config('support.default_country', 'NG'));
    }
}
