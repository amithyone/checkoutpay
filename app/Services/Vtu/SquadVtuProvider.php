<?php

namespace App\Services\Vtu;

use App\Contracts\Vtu\VtuProviderContract;
use App\Services\Squad\SquadVtuApiClient;

/**
 * Squad by GTB — airtime & data only (electricity/TV/betting not supported on this adapter).
 */
final class SquadVtuProvider implements VtuProviderContract
{
    public function __construct(
        private SquadVtuApiClient $client,
    ) {}

    public function providerKey(): string
    {
        return VtuProviderResolver::PROVIDER_SQUAD;
    }

    public function isConfigured(): bool
    {
        if (SettingOverrides::squadVtuEnabledOverride() === false) {
            return false;
        }

        return $this->client->isConfigured();
    }

    public function getBalance(): array
    {
        return [
            'ok' => false,
            'message' => 'Squad does not expose VTU wallet balance on the vending API. Check the Squad dashboard.',
        ];
    }

    public function networksCatalog(): array
    {
        $networks = [];
        foreach (config('squad_vtu.networks', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            $label = (string) ($row['label'] ?? $id);
            if ($id === '') {
                continue;
            }
            $networks[] = ['id' => $id, 'label' => $label];
        }

        return [
            'networks' => $networks,
            'airtime_min' => (float) config('squad_vtu.airtime_min', 50),
            'airtime_max' => (float) config('squad_vtu.airtime_max', 50000),
        ];
    }

    public function billCatalog(): array
    {
        return [
            'electricity_discos' => [],
            'cable_tv_services' => [],
            'betting_services' => [],
            'electricity_min' => 0,
        ];
    }

    public function fetchDataPlans(string $networkId): array
    {
        $squadNetwork = $this->toSquadNetwork($networkId);
        if ($squadNetwork === null) {
            return ['ok' => false, 'message' => 'Unsupported network.'];
        }

        $result = $this->client->fetchDataBundles($squadNetwork);
        if (! ($result['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($result['message'] ?? 'Could not load data plans.'),
                'raw' => $result['raw'] ?? null,
            ];
        }

        $plans = [];
        foreach ($result['plans'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['plan_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $value = trim((string) ($row['bundle_value'] ?? ''));
            $validity = trim((string) ($row['bundle_validity'] ?? $row['bundle_description'] ?? ''));
            $labelParts = array_filter([$value !== '' ? $value : null, $validity !== '' ? $validity : null]);
            $label = $labelParts !== [] ? implode(' · ', $labelParts) : (string) ($row['plan_name'] ?? $code);
            $price = (float) ($row['bundle_price'] ?? 0);

            $plans[] = [
                'variation_id' => $code,
                'label' => $label,
                'price' => $price,
                'available' => true,
            ];
        }

        return [
            'ok' => true,
            'message' => 'OK',
            'plans' => $plans,
            'raw' => $result['raw'] ?? null,
        ];
    }

    public function fetchTvPlans(string $serviceId): array
    {
        return ['ok' => false, 'message' => 'Cable TV is not available via Squad VTU on Checkout yet.'];
    }

    public function verifyElectricityCustomer(string $serviceId, string $meterNumber, string $variationId): array
    {
        return ['ok' => false, 'message' => 'Electricity is not available via Squad VTU on Checkout yet.'];
    }

    public function verifyBillCustomer(string $serviceId, string $customerId, ?string $variationId = null): array
    {
        return ['ok' => false, 'message' => 'Bill verification is not available via Squad VTU on Checkout yet.'];
    }

    public function purchaseAirtime(string $networkId, string $phone11, float $amount): array
    {
        if ($this->toSquadNetwork($networkId) === null) {
            return ['ok' => false, 'message' => 'Unsupported network.'];
        }

        $max = (float) config('squad_vtu.airtime_max', 50000);
        if ($amount > $max) {
            return ['ok' => false, 'message' => 'Amount exceeds maximum airtime.'];
        }

        $result = $this->client->purchaseAirtime($phone11, $amount);
        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        return $this->normalizePurchaseSuccess($result, 'airtime');
    }

    public function purchaseData(string $networkId, string $phone11, int|string $variationId, float $expectedPrice): array
    {
        if ($this->toSquadNetwork($networkId) === null) {
            return ['ok' => false, 'message' => 'Unsupported network.'];
        }

        $planCode = trim((string) $variationId);
        if ($planCode === '' || $planCode === '0') {
            return ['ok' => false, 'message' => 'Invalid data plan.'];
        }

        $result = $this->client->purchaseData($phone11, $planCode, $expectedPrice);
        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        return $this->normalizePurchaseSuccess($result, 'data');
    }

    public function purchaseElectricity(
        string $serviceId,
        string $meterNumber,
        string $phone11,
        float $amount,
        string $variationId,
        ?string $customerName,
    ): array {
        return ['ok' => false, 'message' => 'Electricity is not available via Squad VTU on Checkout yet.'];
    }

    public function purchaseTv(
        string $serviceId,
        string $smartcardNumber,
        int|string $variationId,
        float $amount,
        ?string $customerName,
        string $phone11,
    ): array {
        return ['ok' => false, 'message' => 'Cable TV is not available via Squad VTU on Checkout yet.'];
    }

    public function purchaseBetting(string $serviceId, string $customerId, float $amount, string $phone11): array
    {
        return ['ok' => false, 'message' => 'Betting is not available via Squad VTU on Checkout yet.'];
    }

    public function serviceAllowed(string $serviceId, string $catalogKind): bool
    {
        if ($catalogKind === 'vtu.networks') {
            return $this->toSquadNetwork($serviceId) !== null;
        }

        return false;
    }

    private function toSquadNetwork(string $networkId): ?string
    {
        $id = strtolower(trim($networkId));
        foreach (config('squad_vtu.networks', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (strtolower((string) ($row['id'] ?? '')) === $id) {
                $squad = strtoupper(trim((string) ($row['squad'] ?? '')));

                return $squad !== '' ? $squad : null;
            }
        }

        return null;
    }

    /**
     * @param  array{ok: bool, message: string, data?: mixed, raw?: mixed}  $result
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    private function normalizePurchaseSuccess(array $result, string $kind): array
    {
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $status = strtolower((string) ($data['status'] ?? 'success'));
        $meta = $data['meta'] ?? null;
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : null;
        }
        $vending = is_array($meta) ? strtolower((string) ($meta['vending_status'] ?? '')) : '';

        $pending = in_array($status, ['pending', 'processing'], true)
            || in_array($vending, ['pending', 'processing'], true);

        $message = $pending
            ? 'Purchase submitted; confirmation pending.'
            : (string) ($result['message'] ?? 'OK');

        return [
            'ok' => true,
            'message' => $message,
            'data' => array_merge($data, [
                'provider' => 'squad',
                'product' => $kind,
                'reference' => $data['reference'] ?? $data['transaction_id'] ?? null,
                'pending' => $pending,
            ]),
            'raw' => $result['raw'] ?? null,
        ];
    }
}
