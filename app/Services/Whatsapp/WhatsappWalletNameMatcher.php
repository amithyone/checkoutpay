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

        if (self::tokensMatchUnordered($profile, $bank)) {
            return true;
        }

        $min = self::minScoreToPass();

        return WhatsappWalletCasualSendParser::scoreNameAgainstAccountName($profile, $bank) >= $min
            || WhatsappWalletCasualSendParser::scoreNameAgainstAccountName($bank, $profile) >= $min;
    }

    /**
     * Same given names in any order, with one-letter typos allowed on longer tokens
     * (e.g. "Emmanuel Oluebube Emejulu" vs "OLUEBEUBE EMMANUEL EMEJULU").
     */
    public static function tokensMatchUnordered(string $normalizedA, string $normalizedB): bool
    {
        $a = preg_split('/\s+/u', $normalizedA, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('/\s+/u', $normalizedB, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($a === [] || $b === []) {
            return false;
        }

        sort($a);
        sort($b);
        if ($a === $b) {
            return true;
        }

        $shorter = count($a) <= count($b) ? $a : $b;
        $longer = count($a) <= count($b) ? $b : $a;

        $used = [];
        foreach ($shorter as $token) {
            $found = false;
            foreach ($longer as $i => $candidate) {
                if (isset($used[$i])) {
                    continue;
                }
                if (self::tokensAreClose($token, $candidate)) {
                    $used[$i] = true;
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
    }

    private static function tokensAreClose(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $lenA = strlen($a);
        $lenB = strlen($b);
        if (min($lenA, $lenB) < 4 || abs($lenA - $lenB) > 1) {
            return false;
        }

        return levenshtein($a, $b) <= 1;
    }
}
