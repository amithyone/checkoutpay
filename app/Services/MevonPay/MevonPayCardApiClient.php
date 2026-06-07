<?php

namespace App\Services\MevonPay;

final class MevonPayCardApiClient
{
    public function __construct(
        private MevonPayHttpClient $http,
    ) {}

    public function isConfigured(): bool
    {
        return $this->http->isConfigured();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function createCard(array $payload): array
    {
        $body = array_merge(['action' => 'create'], $payload);

        return $this->postCardEndpoint('/V1/card_request', $body);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function topupCard(float $amountUsd, string $cardCode): array
    {
        return $this->postCardEndpoint('/V1/card_topup', [
            'amount' => round($amountUsd, 2),
            'card_code' => $cardCode,
        ]);
    }

    /**
     * MevonPay docs use raw secret-key auth (same as /V1/balance and /V1/exchange).
     * Bearer is only attempted as a fallback for older integrations.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    private function postCardEndpoint(string $path, array $payload): array
    {
        $raw = $this->http->postJson($path, $payload, 'raw');
        if ($raw['ok'] ?? false) {
            return $raw;
        }

        $bearer = $this->http->postJson($path, $payload, 'bearer');
        if ($bearer['ok'] ?? false) {
            return $bearer;
        }

        return $raw;
    }

    /**
     * @param  'freeze'|'unfreeze'  $action
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function setCardStatus(string $action, string $cardCode): array
    {
        return $this->http->postJson('/V1/card_status', [
            'action' => $action,
            'card_code' => $cardCode,
        ], 'raw');
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function withdrawFromCard(float $amountUsd, string $cardCode, string $reason = 'Withdrawal to Wallet'): array
    {
        return $this->http->postJson('/V1/card_withdraw', [
            'amount' => round($amountUsd, 2),
            'card_code' => $cardCode,
            'reason' => $reason,
        ], 'raw');
    }

    /**
     * Live card spend balance (Mevon docs: POST /V1/card_balance with request_id).
     *
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardBalance(string $requestId): array
    {
        $path = (string) config('virtual_card.mevon_card_balance_path', '/V1/card_balance');

        return $this->postCardEndpoint($path, [
            'request_id' => trim($requestId),
        ]);
    }

    /**
     * Merchant spend / decline history (Mevon docs: POST /V1/card_transactions with card_code).
     *
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardTransactions(string $cardCode): array
    {
        $path = (string) config('virtual_card.mevon_card_transactions_path', '/V1/card_transactions');

        return $this->postCardEndpoint($path, [
            'card_code' => trim($cardCode),
        ]);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardDetails(string $cardId): array
    {
        $path = (string) config('virtual_card.mevon_card_details_path', '/V1/card_details');
        $body = [
            'card_id' => $cardId,
            'card_code' => $cardId,
        ];

        $raw = $this->http->postJson($path, $body, 'raw');
        if ($raw['ok'] ?? false) {
            return $raw;
        }

        return $this->http->postJson($path, $body, 'bearer');
    }
}
