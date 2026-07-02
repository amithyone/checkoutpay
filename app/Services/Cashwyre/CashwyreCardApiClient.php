<?php

namespace App\Services\Cashwyre;

final class CashwyreCardApiClient
{
    public function __construct(
        private CashwyreHttpClient $http,
    ) {}

    public function isConfigured(): bool
    {
        return $this->http->isConfigured();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function createCustomer(array $payload): array
    {
        return $this->http->postJson($this->path('create_customer'), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function createCard(array $payload): array
    {
        return $this->http->postJson($this->path('create_card'), $payload);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function topupCard(float $amountUsd, string $cardCode, ?string $customerId = null): array
    {
        return $this->http->postJson($this->path('topup_card'), array_filter([
            'amount' => round($amountUsd, 2),
            'card_code' => trim($cardCode),
            'card_id' => trim($cardCode),
            'customer_id' => $customerId,
        ], static fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * @param  'freeze'|'unfreeze'  $action
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function setCardStatus(string $action, string $cardCode): array
    {
        return $this->http->postJson($this->path('card_status'), [
            'action' => $action,
            'card_code' => trim($cardCode),
            'card_id' => trim($cardCode),
        ]);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function withdrawFromCard(float $amountUsd, string $cardCode, string $reason = 'Withdrawal to Wallet'): array
    {
        return $this->http->postJson($this->path('withdraw_card'), [
            'amount' => round($amountUsd, 2),
            'card_code' => trim($cardCode),
            'card_id' => trim($cardCode),
            'reason' => $reason,
        ]);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardBalance(string $requestId): array
    {
        return $this->http->postJson($this->path('card_balance'), [
            'request_id' => trim($requestId),
            'reference' => trim($requestId),
        ]);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardDetails(string $cardId): array
    {
        return $this->http->postJson($this->path('card_details'), [
            'card_id' => trim($cardId),
            'card_code' => trim($cardId),
        ]);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardTransactions(string $cardCode): array
    {
        return $this->http->postJson($this->path('card_transactions'), [
            'card_code' => trim($cardCode),
            'card_id' => trim($cardCode),
        ]);
    }

    private function path(string $key): string
    {
        $paths = config('cashwyre.paths', []);

        return (string) ($paths[$key] ?? '/'.$key);
    }
}
