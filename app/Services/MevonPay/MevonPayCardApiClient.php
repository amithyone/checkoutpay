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

        return $this->http->postJson('/V1/card_request', $body, 'bearer');
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function topupCard(float $amountUsd, string $cardCode): array
    {
        return $this->http->postJson('/V1/card_topup', [
            'amount' => round($amountUsd, 2),
            'card_code' => $cardCode,
        ], 'bearer');
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
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardDetails(string $cardCode): array
    {
        $path = (string) config('virtual_card.mevon_card_details_path', '/V1/card_details');

        return $this->http->postJson($path, [
            'card_code' => $cardCode,
        ], 'raw');
    }
}
