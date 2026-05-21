<?php

namespace App\Services\Vtu;

use App\Contracts\Vtu\VtuProviderContract;
use App\Services\MevonPay\MevonPayVtuApiClient;

final class MevonPayVtuProvider implements VtuProviderContract
{
    public function __construct(
        private MevonPayVtuApiClient $client,
        private VtuCatalogNormalizer $normalizer,
    ) {}

    public function providerKey(): string
    {
        return 'mevonpay';
    }

    public function isConfigured(): bool
    {
        if (SettingOverrides::mevonPayVtuEnabledOverride() === false) {
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
        $info = $this->client->airtimeGetInfo();
        $networks = ($info['ok'] ?? false)
            ? $this->normalizer->networks($info['data'] ?? null)
            : config('mevonpay_vtu.networks_fallback', []);

        return [
            'networks' => $networks,
            'airtime_min' => (float) config('mevonpay_vtu.airtime_min', 50),
            'airtime_max' => (float) config('mevonpay_vtu.airtime_max', 50000),
        ];
    }

    public function billCatalog(): array
    {
        $elec = $this->client->electricityGetInfo();
        $cable = $this->client->cableTvGetInfo();
        $bet = $this->client->bettingGetInfo();

        return [
            'electricity_discos' => ($elec['ok'] ?? false)
                ? $this->normalizer->electricityDiscos($elec['data'] ?? null)
                : [],
            'cable_tv_services' => ($cable['ok'] ?? false)
                ? $this->normalizer->cableTvServices($cable['data'] ?? null)
                : [],
            'betting_services' => ($bet['ok'] ?? false)
                ? $this->normalizer->bettingServices($bet['data'] ?? null)
                : [],
            'electricity_min' => (float) config('mevonpay_vtu.electricity_min', 500),
        ];
    }

    public function fetchDataPlans(string $networkId): array
    {
        $info = $this->client->dataGetInfo();
        if (! ($info['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($info['message'] ?? 'Could not load data plans.')];
        }

        $plans = $this->normalizer->dataPlansForProvider($info['data'] ?? null, $networkId);

        return ['ok' => true, 'message' => 'OK', 'plans' => $plans, 'raw' => $info['raw'] ?? null];
    }

    public function fetchTvPlans(string $serviceId): array
    {
        $info = $this->client->cableTvGetInfo();
        if (! ($info['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($info['message'] ?? 'Could not load TV packages.')];
        }

        $plans = $this->normalizer->tvPlansForProvider($info['data'] ?? null, $serviceId);

        return ['ok' => true, 'message' => 'OK', 'plans' => $plans, 'raw' => $info['raw'] ?? null];
    }

    public function verifyElectricityCustomer(string $serviceId, string $meterNumber, string $variationId): array
    {
        return $this->client->verifyElectricity($serviceId, $meterNumber, $variationId);
    }

    public function verifyBillCustomer(string $serviceId, string $customerId, ?string $variationId = null): array
    {
        $planCode = $variationId;
        if ($planCode === null || $planCode === '') {
            $plans = $this->fetchTvPlans($serviceId);
            if (! ($plans['ok'] ?? false) || empty($plans['plans'])) {
                return ['ok' => false, 'message' => 'Could not load TV packages for verification.'];
            }
            $first = $plans['plans'][0];
            $planCode = (string) ($first['variation_id'] ?? '');
            if ($planCode === '') {
                return ['ok' => false, 'message' => 'No TV package available for verification.'];
            }
        }

        return $this->client->verifyCableTv($serviceId, $customerId, (string) $planCode);
    }

    public function purchaseAirtime(string $networkId, string $phone11, float $amount): array
    {
        return $this->client->purchaseAirtime($networkId, $phone11, $amount);
    }

    public function purchaseData(string $networkId, string $phone11, int|string $variationId, float $expectedPrice): array
    {
        return $this->client->purchaseData($networkId, $phone11, (string) $variationId, $expectedPrice);
    }

    public function purchaseElectricity(
        string $serviceId,
        string $meterNumber,
        string $phone11,
        float $amount,
        string $variationId,
        ?string $customerName,
    ): array {
        $name = $customerName ?? 'Customer';

        return $this->client->purchaseElectricity(
            $serviceId,
            $meterNumber,
            $variationId,
            $amount,
            $name,
            $phone11,
        );
    }

    public function purchaseTv(
        string $serviceId,
        string $smartcardNumber,
        int|string $variationId,
        float $amount,
        ?string $customerName,
        string $phone11,
    ): array {
        $name = $customerName ?? 'Customer';

        return $this->client->purchaseCableTv(
            $serviceId,
            $smartcardNumber,
            (string) $variationId,
            $amount,
            $name,
            $phone11,
        );
    }

    public function purchaseBetting(string $serviceId, string $customerId, float $amount, string $phone11): array
    {
        return $this->client->purchaseBetting($serviceId, $customerId, $amount, $phone11);
    }

    public function serviceAllowed(string $serviceId, string $catalogKind): bool
    {
        if ($catalogKind === 'vtu.networks') {
            foreach ($this->networksCatalog()['networks'] as $row) {
                if ((string) ($row['id'] ?? '') === $serviceId) {
                    return true;
                }
            }

            return false;
        }

        $map = [
            'vtu.electricity_discos' => 'electricity_discos',
            'vtu.cable_tv_services' => 'cable_tv_services',
            'vtu.betting_services' => 'betting_services',
        ];
        $key = $map[$catalogKind] ?? null;
        if ($key === null) {
            return false;
        }

        $catalog = $this->billCatalog();

        foreach ($catalog[$key] ?? [] as $row) {
            if ((string) ($row['id'] ?? '') === $serviceId) {
                return true;
            }
        }

        return false;
    }
}
