<?php

namespace App\Services\Whatsapp;

/**
 * Outcome of executing a wallet bank / P2P transfer after PIN or OTP confirmation.
 */
final class WalletTransferCompletionResult
{
    /**
     * @param  array<string, mixed>|null  $receipt  Bank transfer receipt (session_id, response_message, reference, …)
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $message = '',
        public readonly ?float $balanceAfter = null,
        public readonly ?array $receipt = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $receipt
     */
    public static function success(?float $balanceAfter = null, string $message = 'Transfer completed.', ?array $receipt = null): self
    {
        return new self(true, $message, $balanceAfter, $receipt);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }
}
