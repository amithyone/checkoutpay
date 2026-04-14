<?php

namespace App\Services\Whatsapp;

/**
 * Minimal display helpers for WhatsApp wallet amounts (no locale engine).
 */
final class WhatsappWalletMoneyFormatter
{
    public static function symbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'NGN' => '₦',
            'NAD' => 'N$',
            'USD' => '$',
            'CAD' => 'C$',
            'GBP' => '£',
            'GHS' => 'GH₵',
            default => strtoupper($currency).' ',
        };
    }

    public static function format(float $amount, string $currency): string
    {
        $sym = self::symbol($currency);
        $n = number_format($amount, 2);

        return str_ends_with($sym, ' ') ? $sym.$n : $sym.$n;
    }

    /**
     * Human-readable cross-rate: units of credit currency per 1 unit of debit currency.
     */
    public static function crossRateLine(float $debitAmount, string $debitCurrency, float $creditAmount, string $creditCurrency): string
    {
        if ($debitAmount <= 0) {
            return '';
        }
        $from = strtoupper($debitCurrency);
        $to = strtoupper($creditCurrency);
        $per = $creditAmount / $debitAmount;
        $n = self::trimmedDecimalString($per, 6);

        return "1 {$from} ≈ {$n} {$to}";
    }

    private static function trimmedDecimalString(float $x, int $maxDecimals): string
    {
        $s = number_format($x, $maxDecimals, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');

        return $s === '' || $s === '-' ? '0' : $s;
    }
}
