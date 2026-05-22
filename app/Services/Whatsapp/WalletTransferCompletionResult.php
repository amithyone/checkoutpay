<?php

namespace App\Services\Whatsapp;

/**
 * Outcome of executing a wallet bank / P2P transfer after PIN or OTP confirmation.
 */
final class WalletTransferCompletionResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $message = '',
        public readonly ?float $balanceAfter = null,
    ) {}

    public static function success(?float $balanceAfter = null, string $message = 'Transfer completed.'): self
    {
        return new self(true, $message, $balanceAfter);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }
}
