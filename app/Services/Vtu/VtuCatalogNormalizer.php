<?php

namespace App\Services\Vtu;

final class VtuCatalogNormalizer
{
    /**
     * @param  mixed  $getInfoData  MevonPay getInfo `data` payload
     * @return list<array{id: string, label: string}>
     */
    public function electricityDiscos(mixed $getInfoData): array
    {
        return $this->providersFromGetInfo($getInfoData, 'plans');
    }

    /**
     * @param  mixed  $getInfoData
     * @return list<array{id: string, label: string}>
     */
    public function cableTvServices(mixed $getInfoData): array
    {
        $providers = $this->providersFromGetInfo($getInfoData, 'providerPlans');

        return array_map(static function (array $p): array {
            $id = (string) ($p['serviceId'] ?? $p['id']);
            if ($id === '' && isset($p['id'])) {
                $id = (string) $p['id'];
            }

            return [
                'id' => $id !== '' ? $id : (string) $p['id'],
                'label' => (string) ($p['label'] ?? $p['name'] ?? $id),
            ];
        }, $providers);
    }

    /**
     * @param  mixed  $getInfoData
     * @return list<array{id: string, label: string}>
     */
    public function bettingServices(mixed $getInfoData): array
    {
        return $this->providersFromGetInfo($getInfoData, 'plans');
    }

    /**
     * @param  mixed  $getInfoData
     * @return list<array{id: string, label: string}>
     */
    public function networks(mixed $getInfoData): array
    {
        $fromApi = $this->providersFromGetInfo($getInfoData, 'plans');
        if ($fromApi !== []) {
            return $fromApi;
        }

        return config('mevonpay_vtu.networks_fallback', []);
    }

    /**
     * @param  mixed  $getInfoData
     * @return list<array{variation_id: string|int, label: string, price: float, available: bool, service_id?: string}>
     */
    public function dataPlansForProvider(mixed $getInfoData, string $providerCode): array
    {
        return $this->plansForProvider($getInfoData, $providerCode, 'providerPlans');
    }

    /**
     * @param  mixed  $getInfoData
     * @return list<array{variation_id: string|int, label: string, price: float, available: bool, service_id?: string}>
     */
    public function tvPlansForProvider(mixed $getInfoData, string $providerCode): array
    {
        return $this->plansForProvider($getInfoData, $providerCode, 'providerPlans');
    }

    /**
     * @return list<array{id: string, label: string, serviceId?: string}>
     */
    private function providersFromGetInfo(mixed $data, string $planKey): array
    {
        $countries = $this->unwrapCountries($data);
        $out = [];
        foreach ($countries as $country) {
            if (! is_array($country)) {
                continue;
            }
            $providers = $country['providers'] ?? [];
            if (! is_array($providers)) {
                continue;
            }
            foreach ($providers as $provider) {
                if (! is_array($provider)) {
                    continue;
                }
                $code = (string) ($provider['code'] ?? '');
                if ($code === '') {
                    continue;
                }
                $out[] = [
                    'id' => $code,
                    'label' => (string) ($provider['name'] ?? $code),
                    'serviceId' => isset($provider['serviceId']) ? (string) $provider['serviceId'] : null,
                    'planKey' => $planKey,
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<array{variation_id: string|int, label: string, price: float, available: bool, service_id?: string}>
     */
    private function plansForProvider(mixed $data, string $providerCode, string $plansField): array
    {
        $countries = $this->unwrapCountries($data);
        $plans = [];
        foreach ($countries as $country) {
            if (! is_array($country)) {
                continue;
            }
            foreach ($country['providers'] ?? [] as $provider) {
                if (! is_array($provider)) {
                    continue;
                }
                $code = (string) ($provider['code'] ?? '');
                $serviceId = (string) ($provider['serviceId'] ?? $code);
                if ($code !== $providerCode && $serviceId !== $providerCode) {
                    continue;
                }
                $rows = $provider[$plansField] ?? $provider['plans'] ?? [];
                if (! is_array($rows)) {
                    continue;
                }
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $planCode = (string) ($row['code'] ?? '');
                    if ($planCode === '') {
                        continue;
                    }
                    $amount = isset($row['amount']) ? (float) $row['amount'] : 0.0;
                    if ($amount < 0.01 && isset($row['name'])) {
                        $amount = $this->parseAmountFromLabel((string) $row['name']);
                    }
                    $vid = is_numeric($planCode) ? (int) $planCode : $planCode;
                    $plans[] = [
                        'variation_id' => $vid,
                        'label' => (string) ($row['name'] ?? $planCode),
                        'price' => round($amount, 2),
                        'available' => true,
                        'service_id' => $serviceId !== '' ? $serviceId : $code,
                    ];
                }
            }
        }

        return $plans;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unwrapCountries(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (isset($data[0]['providers'])) {
            return $data;
        }

        $inner = $data['data'] ?? $data;
        if (is_array($inner) && isset($inner[0]['providers'])) {
            return $inner;
        }
        if (is_array($inner) && isset($inner['data']) && is_array($inner['data'])) {
            $deepest = $inner['data'];
            if (isset($deepest[0]['providers'])) {
                return $deepest;
            }
        }

        return [];
    }

    private function parseAmountFromLabel(string $label): float
    {
        if (preg_match('/NGN\s*([\d,]+(?:\.\d+)?)/i', $label, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }
        if (preg_match('/\(\s*([\d,]+(?:\.\d+)?)\s*\)/', $label, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }

        return 0.0;
    }
}
