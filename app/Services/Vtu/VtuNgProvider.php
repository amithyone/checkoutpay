<?php

namespace App\Services\Vtu;

use App\Contracts\Vtu\VtuProviderContract;
use App\Services\VtuNg\VtuNgApiClient;

final class VtuNgProvider implements VtuProviderContract
{
    public function __construct(
        private VtuNgApiClient $client,
    ) {}

    public function providerKey(): string
    {
        return 'vtu_ng';
    }

    public function isConfigured(): bool
    {
        if (SettingOverrides::vtuNgEnabledOverride() === false) {
            return false;
        }

        return $this->client->isConfigured();
    }

    public function getBalance(): array
    {
        return $this->client->getBalance();
    }

    public function networksCatalog(): array
    {
        return [
            'networks' => config('vtu.networks', []),
            'airtime_min' => (float) config('vtu.airtime_min', 50),
            'airtime_max' => (float) config('vtu.airtime_max', 50000),
        ];
    }

    public function billCatalog(): array
    {
        return [
            'electricity_discos' => config('vtu.electricity_discos', []),
            'cable_tv_services' => config('vtu.cable_tv_services', []),
            'betting_services' => config('vtu.betting_services', []),
            'electricity_min' => (float) config('vtu.electricity_min', 500),
        ];
    }

    public function fetchDataPlans(string $networkId): array
    {
        return $this->client->fetchDataPlans($networkId);
    }

    public function fetchTvPlans(string $serviceId): array
    {
        return $this->client->fetchTvPlans($serviceId);
    }

    public function verifyElectricityCustomer(string $serviceId, string $meterNumber, string $variationId): array
    {
        return $this->client->verifyElectricityCustomer($serviceId, $meterNumber, $variationId);
    }

    public function verifyBillCustomer(string $serviceId, string $customerId, ?string $variationId = null): array
    {
        return $this->client->verifyBillCustomer($serviceId, $customerId, $variationId);
    }

    public function purchaseAirtime(string $networkId, string $phone11, float $amount): array
    {
        return $this->client->purchaseAirtime($networkId, $phone11, $amount);
    }

    public function purchaseData(string $networkId, string $phone11, int|string $variationId, float $expectedPrice): array
    {
        return $this->client->purchaseData($networkId, $phone11, (int) $variationId);
    }

    public function purchaseElectricity(
        string $serviceId,
        string $meterNumber,
        string $phone11,
        float $amount,
        string $variationId,
        ?string $customerName,
    ): array {
        return $this->client->purchaseElectricity($serviceId, $meterNumber, $phone11, $amount, $variationId);
    }

    public function purchaseTv(
        string $serviceId,
        string $smartcardNumber,
        int|string $variationId,
        float $amount,
        ?string $customerName,
        string $phone11,
    ): array {
        return $this->client->purchaseTv($serviceId, $smartcardNumber, $variationId, $amount);
    }

    public function purchaseBetting(string $serviceId, string $customerId, float $amount, string $phone11): array
    {
        return $this->client->purchaseBetting($serviceId, $customerId, $amount);
    }

    public function serviceAllowed(string $serviceId, string $catalogKind): bool
    {
        $map = [
            'vtu.electricity_discos' => 'electricity_discos',
            'vtu.cable_tv_services' => 'cable_tv_services',
            'vtu.betting_services' => 'betting_services',
        ];

        if ($catalogKind === 'vtu.networks') {
            foreach ($this->networksCatalog()['networks'] as $row) {
                if ((string) ($row['id'] ?? '') === $serviceId) {
                    return true;
                }
            }

            return false;
        }

        $key = $map[$catalogKind] ?? null;
        if ($key === null) {
            return false;
        }

        foreach ($this->billCatalog()[$key] ?? [] as $row) {
            if ((string) ($row['id'] ?? '') === $serviceId) {
                return true;
            }
        }

        return false;
    }
}
