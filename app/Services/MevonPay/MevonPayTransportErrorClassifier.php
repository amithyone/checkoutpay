<?php

namespace App\Services\MevonPay;

use Illuminate\Http\Client\ConnectionException;

/**
 * Distinguishes ambiguous transport failures (timeout, DNS, connection reset) from
 * confirmed provider failures. Ambiguous errors must not trigger wallet refunds because
 * the payout may still succeed asynchronously at MevonPay.
 */
class MevonPayTransportErrorClassifier
{
    public static function isAmbiguousTransportFailure(?\Throwable $throwable = null, ?string $message = null): bool
    {
        if ($throwable instanceof ConnectionException) {
            return true;
        }

        $message = strtolower(trim((string) ($message ?? $throwable?->getMessage() ?? '')));
        if ($message === '') {
            return true;
        }

        $needles = [
            'curl error 28',
            'curl error 6',
            'curl error 7',
            'curl error 52',
            'curl error 56',
            'timed out',
            'timeout',
            'time limit exceeded',
            'maximum execution time',
            'connection timed out',
            'operation timed out',
            'could not resolve host',
            'connection refused',
            'connection reset',
            'empty reply from server',
            'ssl connection timeout',
            'transfer closed with outstanding read data remaining',
        ];

        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
