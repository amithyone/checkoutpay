<?php

namespace App\Services;

/**
 * Normalize bank codes to NIP-style 6-digit strings for MevonPay createtransfer / name enquiry.
 */
class NigerianBankCodeNormalizer
{
    /**
     * Convert a stored or user-entered bank code to the best NIP 6-digit form for payouts.
     */
    public static function toNipTransferCode(string $bankCode): string
    {
        $raw = trim($bankCode);
        if ($raw === '') {
            return $raw;
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            return $raw;
        }

        $legacyMap = config('nigerian_bank_legacy_to_nip', []);
        if (! is_array($legacyMap)) {
            $legacyMap = [];
        }

        if (strlen($digits) <= 4) {
            $p3 = str_pad($digits, 3, '0', STR_PAD_LEFT);
            if (isset($legacyMap[$p3]) && is_string($legacyMap[$p3]) && $legacyMap[$p3] !== '') {
                return self::padSixDigits($legacyMap[$p3]);
            }
            $p4 = str_pad($digits, 4, '0', STR_PAD_LEFT);
            if (isset($legacyMap[$p4]) && is_string($legacyMap[$p4]) && $legacyMap[$p4] !== '') {
                return self::padSixDigits($legacyMap[$p4]);
            }
        }

        if (strlen($digits) >= 6) {
            return str_pad($digits, 6, '0', STR_PAD_LEFT);
        }

        if (strlen($digits) === 5) {
            return str_pad($digits, 6, '0', STR_PAD_LEFT);
        }

        if (strlen($digits) <= 3 && isset($legacyMap[$digits]) && is_string($legacyMap[$digits]) && $legacyMap[$digits] !== '') {
            return self::padSixDigits($legacyMap[$digits]);
        }

        return str_pad($digits, 6, '0', STR_PAD_LEFT);
    }

    private static function padSixDigits(string $digitsOnly): string
    {
        $d = preg_replace('/\D/', '', $digitsOnly) ?? '';

        return str_pad($d, 6, '0', STR_PAD_LEFT);
    }
}
