<?php

namespace App\Services\Whatsapp;

class PhoneNormalizer
{
    /**
     * WhatsApp remote JID e.g. 2348012345678@s.whatsapp.net → digits only (no +) for storage/compare.
     */
    public static function e164FromRemoteJid(?string $remoteJid): ?string
    {
        if ($remoteJid === null || $remoteJid === '') {
            return null;
        }

        $local = strtolower(trim(explode('@', $remoteJid, 2)[0] ?? ''));
        if ($local === '' || str_contains($local, ':')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $local);

        return $digits !== '' ? $digits : null;
    }

    /**
     * Normalize user-typed phone or email check — for optional future use.
     */
    public static function digitsOnly(?string $input): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $input);

        return $digits !== '' ? $digits : null;
    }

    /**
     * Compare two phone inputs (e.g. WhatsApp sender vs user-typed) for same Nigerian mobile.
     */
    public static function sameNigeriaMobile(?string $senderE164Digits, ?string $userTyped): bool
    {
        $a = self::canonicalNgE164Digits($senderE164Digits);
        $b = self::canonicalNgE164Digits(self::digitsOnly($userTyped));

        return $a !== null && $b !== null && $a === $b;
    }

    /**
     * Normalize to digits starting with 234 (13 chars for standard NG mobile).
     */
    public static function canonicalNgE164Digits(?string $digits): ?string
    {
        $d = self::digitsOnly($digits);
        if ($d === null) {
            return null;
        }

        if (str_starts_with($d, '234') && strlen($d) === 13) {
            return $d;
        }

        if (str_starts_with($d, '0') && strlen($d) === 11) {
            return '234'.substr($d, 1);
        }

        if (strlen($d) === 10 && $d[0] !== '0') {
            return '234'.$d;
        }

        return $d;
    }

    /**
     * Detect a message that is only a Nigerian mobile (for P2P shortcut from menus).
     * Accepts, after stripping non-digits:
     * - 11 digits starting with 0 (local, e.g. 080…)
     * - 10 digits not starting with 0 (national without 0, e.g. 80…)
     * - 13 digits starting with 234 (e.g. +234… or 234… once + and spaces are removed)
     * Ignores spaces, dashes, parentheses, leading +, and common WhatsApp bold markers.
     */
    public static function parseBareNigerianMobileForP2pShortcut(string $text): ?string
    {
        $t = trim($text);
        $t = preg_replace('/^[\*_~\s]+|[\*_~\s]+$/u', '', $t) ?? $t;
        if (preg_match('/[a-zA-Z]/u', $t)) {
            return null;
        }
        $d = self::digitsOnly($t);
        if ($d === null) {
            return null;
        }
        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            return self::canonicalNgE164Digits($d);
        }
        if (strlen($d) === 13 && str_starts_with($d, '234')) {
            return self::canonicalNgE164Digits($d);
        }
        if (strlen($d) === 10 && $d[0] !== '0') {
            $c = self::canonicalNgE164Digits($d);

            return ($c !== null && strlen($c) === 13 && str_starts_with($c, '234')) ? $c : null;
        }

        return null;
    }

    /**
     * Mevon/Rubies examples use 08123456789 style.
     */
    public static function e164DigitsToNgLocal11(?string $e164Digits): ?string
    {
        $d = self::canonicalNgE164Digits($e164Digits);
        if ($d === null || strlen($d) !== 13 || ! str_starts_with($d, '234')) {
            return null;
        }

        return '0'.substr($d, 3);
    }
}
