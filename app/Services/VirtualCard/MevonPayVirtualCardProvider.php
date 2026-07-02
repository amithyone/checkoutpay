<?php

namespace App\Services\VirtualCard;

use App\Contracts\VirtualCard\VirtualCardProviderContract;
use App\Services\MevonPay\MevonPayCardApiClient;

final class MevonPayVirtualCardProvider implements VirtualCardProviderContract
{
    public function __construct(
        private MevonPayCardApiClient $client,
    ) {}

    public function providerKey(): string
    {
        return VirtualCardProviderResolver::PROVIDER_MEVONPAY;
    }

    public function isConfigured(): bool
    {
        if (VirtualCardSettingOverrides::mevonPayCardEnabledOverride() === false) {
            return false;
        }

        return $this->client->isConfigured();
    }

    public function requiresUsdPrefunding(): bool
    {
        return true;
    }

    public function createCard(array $payload): array
    {
        return $this->client->createCard($payload);
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
