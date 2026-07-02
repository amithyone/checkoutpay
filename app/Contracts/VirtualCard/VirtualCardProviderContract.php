<?php

namespace App\Contracts\VirtualCard;

interface VirtualCardProviderContract
{
    public function providerKey(): string;

    public function isConfigured(): bool;

    /** Whether Checkout must prefund merchant USD float before card API calls (MevonPay). */
    public function requiresUsdPrefunding(): bool;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function createCard(array $payload): array;

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function topupCard(float $amountUsd, string $cardCode): array;

    /**
     * @param  'freeze'|'unfreeze'  $action
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function setCardStatus(string $action, string $cardCode): array;

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function withdrawFromCard(float $amountUsd, string $cardCode, string $reason = 'Withdrawal to Wallet'): array;

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardBalance(string $requestId): array;

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardDetails(string $cardId): array;

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getCardTransactions(string $cardCode): array;
}
