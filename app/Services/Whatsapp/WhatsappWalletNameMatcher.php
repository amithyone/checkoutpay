<?php

namespace App\Services\Whatsapp;

/**
 * Normalizes person names and scores them against bank account names (Mevon/Rubies).
 */
final class WhatsappWalletNameMatcher
{
    public static function normalizePersonName(string $name): string
    {
        $n = mb_strtolower(trim($name));
        $n = preg_replace('/\b(mr|mrs|ms|miss|dr|chief|alhaji|alh|engr|bar)\b\.?/iu', '', $n) ?? $n;
        $n = preg_replace('/[^a-z0-9\s]/u', ' ', $n) ?? $n;
        $n = preg_replace('/\s+/u', ' ', $n) ?? $n;

        return trim($n);
    }

    public static function minScoreToPass(): int
    {
        return max(50, min(100, (int) config('whatsapp.wallet.pin_reset_name_min_score', 60)));
    }

    public static function passes(string $profileName, string $bankAccountName): bool
    {
        $profile = self::normalizePersonName($profileName);
        $bank = self::normalizePersonName($bankAccountName);
        if ($profile === '' || $bank === '') {
            return false;
        }

        $min = self::minScoreToPass();

        return WhatsappWalletCasualSendParser::scoreNameAgainstAccountName($profile, $bank) >= $min
            || WhatsappWalletCasualSendParser::scoreNameAgainstAccountName($bank, $profile) >= $min;
    }
}
