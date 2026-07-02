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
        $requestId = trim((string) ($payload['reference'] ?? ''));

        return $this->client->createCard($payload, $requestId !== '' ? $requestId : null);
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
}
