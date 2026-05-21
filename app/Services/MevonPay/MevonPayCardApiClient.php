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
}
