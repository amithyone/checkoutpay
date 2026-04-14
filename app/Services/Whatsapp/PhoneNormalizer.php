<?php

namespace App\Services\Whatsapp;

/**
 * E.164 digits only (no leading +). Nigeria, Namibia, Ghana, UK, NANP (+1) for WhatsApp wallet P2P.
 */
final class PhoneNormalizer
{
    public static function digitsOnly(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }
        $d = preg_replace('/\D+/', '', $s) ?? '';

        return $d === '' ? null : $d;
    }

    /**
     * Nigeria: returns 234XXXXXXXXXX (13 digits) or null.
     */
    public static function canonicalNgE164Digits(string $input): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }
        if (strlen($d) === 13 && str_starts_with($d, '234')) {
            return $d;
        }
        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            return '234'.substr($d, 1);
        }
        if (strlen($d) === 10 && $d[0] !== '0') {
            return '234'.$d;
        }

        return null;
    }

    /**
     * Namibia: returns 264 + national significant number (typical mobile 81/82/85…).
     */
    public static function canonicalNaE164Digits(string $input): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }
        if (str_starts_with($d, '264')) {
            $rest = substr($d, 3);

            return strlen($rest) >= 8 && strlen($rest) <= 10 ? $d : null;
        }
        if (strlen($d) === 10 && str_starts_with($d, '0') && $d[1] === '8') {
            return '264'.substr($d, 1);
        }
        if (strlen($d) === 9 && $d[0] === '8') {
            return '264'.$d;
        }

        return null;
    }

    /**
     * Ghana: 233 + 9-digit mobile.
     */
    public static function canonicalGhE164Digits(string $input): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }
        if (str_starts_with($d, '233')) {
            $rest = substr($d, 3);

            return strlen($rest) === 9 ? $d : null;
        }
        if (strlen($d) === 10 && str_starts_with($d, '0')) {
            return '233'.substr($d, 1);
        }

        return null;
    }

    /**
     * United Kingdom: 44 + national significant number (typically 10 digits for mobiles).
     */
    public static function canonicalGbE164Digits(string $input): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }
        if (str_starts_with($d, '44')) {
            $rest = substr($d, 2);

            return strlen($rest) >= 9 && strlen($rest) <= 10 ? $d : null;
        }
        if (strlen($d) >= 10 && strlen($d) <= 11 && str_starts_with($d, '0')) {
            return '44'.substr($d, 1);
        }

        return null;
    }

    /**
     * US/Canada NANP: 1 + 10 digits (stored as 11 digits with country code 1).
     */
    public static function canonicalNanpE164Digits(string $input): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }
        if (str_starts_with($d, '1') && strlen($d) === 11) {
            return $d;
        }
        if (strlen($d) === 10) {
            return '1'.$d;
        }

        return null;
    }

    /**
     * Resolve wallet P2P recipient numbers across supported regions.
     * 10-digit strings without a country code: leading 7/8/9 are treated as Nigeria (backward compatible);
     * other leading digits are treated as NANP (+1) first, then Nigeria as a fallback.
     */
    public static function canonicalInternationalWalletRecipientDigits(string $input): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }

        if (str_starts_with($d, '234')) {
            return self::canonicalNgE164Digits($input);
        }
        if (str_starts_with($d, '264')) {
            return self::canonicalNaE164Digits($input);
        }
        if (str_starts_with($d, '233')) {
            return self::canonicalGhE164Digits($input);
        }
        if (str_starts_with($d, '44')) {
            return self::canonicalGbE164Digits($input);
        }
        if (str_starts_with($d, '1') && strlen($d) === 11) {
            return self::canonicalNanpE164Digits($input);
        }

        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            $gb = self::canonicalGbE164Digits($input);
            if ($gb !== null) {
                return $gb;
            }

            return self::canonicalNgE164Digits($input)
                ?? self::canonicalNaE164Digits($input)
                ?? self::canonicalGhE164Digits($input);
        }

        if (strlen($d) === 10 && $d[0] !== '0') {
            // Nigerian mobiles without trunk 0: 70/71/80–89/90/91… — avoid mis-reading US 10-digit (+1) numbers.
            if (preg_match('/^(70|71|72|73|74|75|76|77|78|79|80|81|82|83|84|85|86|87|88|89|90|91)\d{8}$/', $d) === 1) {
                $ng = self::canonicalNgE164Digits($input);
                if ($ng !== null) {
                    return $ng;
                }
            }
            $nanp = self::canonicalNanpE164Digits($input);
            if ($nanp !== null) {
                return $nanp;
            }

            return self::canonicalNgE164Digits($input);
        }

        return self::canonicalNaE164Digits($input)
            ?? self::canonicalGhE164Digits($input)
            ?? self::canonicalGbE164Digits($input)
            ?? self::canonicalNanpE164Digits($input);
    }

    public static function canonicalE164ForCountry(string $input, string $countryIso): ?string
    {
        return match (strtoupper($countryIso)) {
            'NG' => self::canonicalNgE164Digits($input),
            'NA' => self::canonicalNaE164Digits($input),
            'GH' => self::canonicalGhE164Digits($input),
            'GB', 'UK' => self::canonicalGbE164Digits($input),
            'US', 'CA' => self::canonicalNanpE164Digits($input),
            default => null,
        };
    }
}
