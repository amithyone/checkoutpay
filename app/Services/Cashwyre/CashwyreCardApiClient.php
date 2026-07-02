<?php

namespace App\Services\Cashwyre;

final class CashwyreCardApiClient
{
    public function __construct(
        private CashwyreHttpClient $http,
        private CashwyrePayloadMapper $mapper,
    ) {}

    public function isConfigured(): bool
    {
        return $this->http->isConfigured();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function createCustomer(array $payload, ?string $requestId = null): array
    {
        return $this->http->postJson($this->path('create_customer'), $payload, $requestId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function createCard(array $payload, ?string $requestId = null): array
    {
        $mapped = $this->mapper->createCardPayload($payload);

        return $this->http->postJson($this->path('create_card'), $mapped, $requestId ?? (string) ($payload['reference'] ?? null));
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function topupCard(float $amountUsd, string $cardCode, ?string $requestId = null): array
    {
        return $this->http->postJson($this->path('topup_card'), [
            'cardCode' => trim($cardCode),
            'amountInUSD' => round($amountUsd, 2),
        ], $requestId);
    }

    /**
     * @param  'freeze'|'unfreeze'  $action
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function setCardStatus(string $action, string $cardCode, ?string $requestId = null): array
    {
        $pathKey = $action === 'unfreeze' ? 'unfreeze_card' : 'freeze_card';

        return $this->http->postJson($this->path($pathKey), [
            'cardCode' => trim($cardCode),
        ], $requestId);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function withdrawFromCard(float $amountUsd, string $cardCode, string $reason = 'Withdrawal to Wallet', ?string $requestId = null): array
    {
        return $this->http->postJson($this->path('withdraw_card'), [
            'cardCode' => trim($cardCode),
            'amountInUSD' => round($amountUsd, 2),
            'reason' => $reason,
        ], $requestId);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardBalance(string $cardCode, ?string $requestId = null): array
    {
        return $this->getCardDetails($cardCode, $requestId);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardDetails(string $cardCode, ?string $requestId = null): array
    {
        return $this->http->postJson($this->path('card_details'), [
            'cardCode' => trim($cardCode),
        ], $requestId);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardTransactions(string $cardCode, ?string $requestId = null): array
    {
        return $this->http->postJson($this->path('card_transactions'), [
            'cardCode' => trim($cardCode),
        ], $requestId);
    }

    private function path(string $key): string
    {
        $paths = config('cashwyre.paths', []);

        return (string) ($paths[$key] ?? '/'.$key);
    }
}
