<?php

namespace App\Services\VirtualCard;

use App\Contracts\VirtualCard\VirtualCardProviderContract;
use App\Services\Cashwyre\CashwyreCardApiClient;

final class CashwyreVirtualCardProvider implements VirtualCardProviderContract
{
    public function __construct(
        private CashwyreCardApiClient $client,
    ) {}

    public function providerKey(): string
    {
        return VirtualCardProviderResolver::PROVIDER_CASHWYRE;
    }

    public function isConfigured(): bool
    {
        if (VirtualCardSettingOverrides::cashwyreCardEnabledOverride() === false) {
            return false;
        }

        return $this->client->isConfigured();
    }

    public function requiresUsdPrefunding(): bool
    {
        return false;
    }

    public function createCard(array $payload): array
    {
        $customerPayload = $this->customerPayloadFromCardPayload($payload);
        $customer = $this->client->createCustomer($customerPayload);
        if (! ($customer['ok'] ?? false)) {
            return $customer;
        }

        $customerData = is_array($customer['data'] ?? null) ? $customer['data'] : [];
        $customerId = trim((string) (
            $customerData['customer_id']
            ?? $customerData['customerId']
            ?? $customerData['id']
            ?? ''
        ));

        if ($customerId === '') {
            return [
                'ok' => false,
                'message' => 'Cashwyre customer created but customer_id was missing.',
                'raw' => $customer['raw'] ?? null,
            ];
        }

        $brand = strtoupper((string) config('cashwyre.default_card_brand', 'VISA'));

        return $this->client->createCard([
            'customer_id' => $customerId,
            'customerId' => $customerId,
            'brand' => $brand,
            'name_on_card' => (string) ($payload['cardName'] ?? trim(((string) ($payload['firstName'] ?? '')).' '.((string) ($payload['lastName'] ?? '')))),
            'card_name' => (string) ($payload['cardName'] ?? ''),
            'amount' => round((float) ($payload['amount'] ?? 0), 2),
            'reference' => (string) ($payload['reference'] ?? ''),
        ]);
    }

    public function topupCard(float $amountUsd, string $cardCode): array
    {
        return $this->client->topupCard($amountUsd, $cardCode);
    }

    public function setCardStatus(string $action, string $cardCode): array
    {
        return $this->client->setCardStatus($action, $cardCode);
    }

    public function withdrawFromCard(float $amountUsd, string $cardCode, string $reason = 'Withdrawal to Wallet'): array
    {
        return $this->client->withdrawFromCard($amountUsd, $cardCode, $reason);
    }

    public function getCardBalance(string $requestId): array
    {
        return $this->client->getCardBalance($requestId);
    }

    public function getCardDetails(string $cardId): array
    {
        return $this->client->getCardDetails($cardId);
    }

    public function getCardTransactions(string $cardCode): array
    {
        return $this->client->getCardTransactions($cardCode);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function customerPayloadFromCardPayload(array $payload): array
    {
        return array_filter([
            'first_name' => $payload['firstName'] ?? null,
            'last_name' => $payload['lastName'] ?? null,
            'firstName' => $payload['firstName'] ?? null,
            'lastName' => $payload['lastName'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone_number' => $payload['phoneNumber'] ?? null,
            'phoneNumber' => $payload['phoneNumber'] ?? null,
            'date_of_birth' => $payload['dob'] ?? null,
            'dob' => $payload['dob'] ?? null,
            'home_number' => $payload['homeNumber'] ?? null,
            'homeNumber' => $payload['homeNumber'] ?? null,
            'home_address' => $payload['homeAddress'] ?? null,
            'homeAddress' => $payload['homeAddress'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
    }
}
