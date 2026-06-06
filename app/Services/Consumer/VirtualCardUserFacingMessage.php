<?php

namespace App\Services\Consumer;

final class VirtualCardUserFacingMessage
{
    public static function requestFailedRefunded(): string
    {
        return 'Dollar Virtual Card request could not be completed. Your fee has been refunded.';
    }

    public static function cardPreparing(): string
    {
        return 'We are preparing your Dollar Virtual Card. This usually takes a few minutes — do not submit another request.';
    }

    public static function requestAlreadyInProgress(): string
    {
        return 'You already have a card request in progress. We will notify you when it is ready.';
    }

    public static function topupFailedRefunded(): string
    {
        return 'Card top-up could not be completed. Your wallet has been refunded.';
    }

    public static function serviceUnavailable(): string
    {
        return 'Dollar Virtual Card is temporarily unavailable. Please try again shortly.';
    }

    /**
     * Hide merchant float / MevonPay USD errors from end users.
     */
    public static function sanitizeProviderMessage(
        string $raw,
        string $fallback,
        bool $treatInsufficientUsdAsInternal = true,
    ): string {
        if (self::isInternalOperationalError($raw, $treatInsufficientUsdAsInternal)) {
            return $fallback;
        }

        $trimmed = trim($raw);

        return $trimmed !== '' ? $trimmed : $fallback;
    }

    public static function isInternalOperationalError(string $message, bool $treatInsufficientUsdAsInternal = true): bool
    {
        $haystack = strtolower(trim($message));
        if ($haystack === '') {
            return false;
        }

        if (str_contains($haystack, 'mevonpay')) {
            return true;
        }

        if (str_contains($haystack, 'auto usd') || str_contains($haystack, 'auto-buy')) {
            return true;
        }

        if (str_contains($haystack, 'convert ngn to usd') || str_contains($haystack, 'usd float')) {
            return true;
        }

        if ($treatInsufficientUsdAsInternal
            && str_contains($haystack, 'insufficient')
            && (str_contains($haystack, 'usd') || str_contains($haystack, 'dollar'))) {
            return true;
        }

        if (str_contains($haystack, 'usd top-up') || str_contains($haystack, 'fund mevonpay')) {
            return true;
        }

        return false;
    }
}
