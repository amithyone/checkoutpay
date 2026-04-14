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
}
