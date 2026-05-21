<?php

namespace App\Contracts\Vtu;

interface VtuProviderContract
{
    public function providerKey(): string;

    public function isConfigured(): bool;

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getBalance(): array;

    /**
     * @return array{networks: list<array{id: string, label: string}>, airtime_min: float, airtime_max: float}
     */
    public function networksCatalog(): array;

    /**
     * @return array{
     *   electricity_discos: list<array{id: string, label: string}>,
     *   cable_tv_services: list<array{id: string, label: string}>,
     *   betting_services: list<array{id: string, label: string}>,
     *   electricity_min: float
     * }
     */
    public function billCatalog(): array;

    /**
     * @return array{ok: bool, message: string, plans?: list<array{variation_id: int|string, label: string, price: float, available: bool}>, raw?: mixed}
     */
    public function fetchDataPlans(string $networkId): array;

    /**
     * @return array{ok: bool, message: string, plans?: list<array{variation_id: int|string, label: string, price: float, available: bool, service_id?: string}>, raw?: mixed}
     */
    public function fetchTvPlans(string $serviceId): array;

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>|null, raw?: mixed}
     */
    public function verifyElectricityCustomer(string $serviceId, string $meterNumber, string $variationId): array;

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>|null, raw?: mixed}
     */
    public function verifyBillCustomer(string $serviceId, string $customerId, ?string $variationId = null): array;

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseAirtime(string $networkId, string $phone11, float $amount): array;

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseData(string $networkId, string $phone11, int|string $variationId, float $expectedPrice): array;

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseElectricity(
        string $serviceId,
        string $meterNumber,
        string $phone11,
        float $amount,
        string $variationId,
        ?string $customerName,
    ): array;

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseTv(
        string $serviceId,
        string $smartcardNumber,
        int|string $variationId,
        float $amount,
        ?string $customerName,
        string $phone11,
    ): array;

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseBetting(string $serviceId, string $customerId, float $amount, string $phone11): array;

    public function serviceAllowed(string $serviceId, string $catalogKind): bool;
}
