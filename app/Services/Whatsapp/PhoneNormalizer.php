<?php

namespace App\Services\Whatsapp;

/**
 * E.164 digits only (no leading +). Nigeria, Namibia, Ghana, ZM/ZW/BW/BJ/TZ/ZA, UK, NANP (+1) for WhatsApp wallet P2P.
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
     * Zambia: 260 + 9-digit national.
     */
    public static function canonicalZmE164Digits(string $input): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }
        if (str_starts_with($d, '260')) {
            $rest = substr($d, 3);

            return strlen($rest) === 9 ? $d : null;
        }
        if (strlen($d) === 10 && str_starts_with($d, '0')) {
            return '260'.substr($d, 1);
        }
        if (strlen($d) === 9) {
            return '260'.$d;
        }

        return null;
    }

    /**
     * Zimbabwe: 263 + 9-digit national.
     */
    public static function canonicalZwE164Digits(string $input): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }
        if (str_starts_with($d, '263')) {
            $rest = substr($d, 3);

            return strlen($rest) === 9 ? $d : null;
        }
        if (strlen($d) === 10 && str_starts_with($d, '0')) {
            return '263'.substr($d, 1);
        }
        if (strlen($d) === 9) {
            return '263'.$d;
        }

        return null;
    }

    /**
     * Botswana: 267 + 8-digit national.
     */
    public static function canonicalBwE164Digits(string $input): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }
        if (str_starts_with($d, '267')) {
            $rest = substr($d, 3);

            return strlen($rest) === 8 ? $d : null;
        }
        if (strlen($d) === 9 && str_starts_with($d, '0')) {
            return '267'.substr($d, 1);
        }
        if (strlen($d) === 8) {
            return '267'.$d;
        }

        return null;
    }

    /**
     * Tanzania: 255 + 9-digit national.
     */
    public static function canonicalTzE164Digits(string $input): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }
        if (str_starts_with($d, '255')) {
            $rest = substr($d, 3);

            return strlen($rest) === 9 ? $d : null;
        }
        if (strlen($d) === 10 && str_starts_with($d, '0')) {
            return '255'.substr($d, 1);
        }
        if (strlen($d) === 9) {
            return '255'.$d;
        }

        return null;
    }

    /**
     * Benin: 229 + 8-digit national.
     */
    public static function canonicalBjE164Digits(string $input): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }
        if (str_starts_with($d, '229')) {
            $rest = substr($d, 3);

            return strlen($rest) === 8 ? $d : null;
        }
        if (strlen($d) === 9 && str_starts_with($d, '0')) {
            return '229'.substr($d, 1);
        }
        if (strlen($d) === 8) {
            return '229'.$d;
        }

        return null;
    }

    /**
     * South Africa: 27 + 9-digit national (E.164 length 11).
     */
    public static function canonicalZaE164Digits(string $input): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }
        if (str_starts_with($d, '27')) {
            $rest = substr($d, 2);

            return strlen($rest) === 9 ? $d : null;
        }
        if (strlen($d) === 10 && str_starts_with($d, '0')) {
            return '27'.substr($d, 1);
        }
        if (strlen($d) === 9) {
            return '27'.$d;
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

        if (str_starts_with($d, '260')) {
            return self::canonicalZmE164Digits($input);
        }
        if (str_starts_with($d, '263')) {
            return self::canonicalZwE164Digits($input);
        }
        if (str_starts_with($d, '264')) {
            return self::canonicalNaE164Digits($input);
        }
        if (str_starts_with($d, '267')) {
            return self::canonicalBwE164Digits($input);
        }
        if (str_starts_with($d, '255')) {
            return self::canonicalTzE164Digits($input);
        }
        if (str_starts_with($d, '234')) {
            return self::canonicalNgE164Digits($input);
        }
        if (str_starts_with($d, '233')) {
            return self::canonicalGhE164Digits($input);
        }
        if (str_starts_with($d, '229')) {
            return self::canonicalBjE164Digits($input);
        }
        if (str_starts_with($d, '44')) {
            return self::canonicalGbE164Digits($input);
        }
        if (str_starts_with($d, '1') && strlen($d) === 11) {
            return self::canonicalNanpE164Digits($input);
        }
        if (str_starts_with($d, '27')) {
            return self::canonicalZaE164Digits($input);
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

        return self::canonicalZmE164Digits($input)
            ?? self::canonicalZwE164Digits($input)
            ?? self::canonicalBwE164Digits($input)
            ?? self::canonicalTzE164Digits($input)
            ?? self::canonicalBjE164Digits($input)
            ?? self::canonicalZaE164Digits($input)
            ?? self::canonicalNaE164Digits($input)
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
            'ZM' => self::canonicalZmE164Digits($input),
            'ZW' => self::canonicalZwE164Digits($input),
            'BW' => self::canonicalBwE164Digits($input),
            'TZ' => self::canonicalTzE164Digits($input),
            'BJ' => self::canonicalBjE164Digits($input),
            'ZA' => self::canonicalZaE164Digits($input),
            'GB', 'UK' => self::canonicalGbE164Digits($input),
            'US', 'CA' => self::canonicalNanpE164Digits($input),
            default => null,
        };
    }

    /**
     * Parse a recipient number using sender-country defaults:
     * - If input already includes a known country code, keep existing behavior.
     * - If input is local/no country code, normalize using sender country first.
     */
    public static function canonicalWalletRecipientForSender(string $input, ?string $senderPhoneE164): ?string
    {
        $d = self::digitsOnly($input);
        if ($d === null) {
            return null;
        }

        $explicit = self::canonicalInternationalWalletRecipientDigits($input);
        if ($explicit !== null && self::looksExplicitInternational($d)) {
            return $explicit;
        }

        $senderCountry = self::countryFromE164($senderPhoneE164 ?? '');
        if ($senderCountry !== null) {
            $senderLocal = self::canonicalE164ForCountry($input, $senderCountry);
            if ($senderLocal !== null) {
                return $senderLocal;
            }
        }

        return $explicit;
    }

    /**
     * Baileys / Evolution {@code remoteJid} (e.g. 234...@s.whatsapp.net) → E.164 digits only, no leading +.
     * Returns null for groups, LID addresses, or non-standard JIDs.
     */
    public static function e164FromRemoteJid(string $remoteJid): ?string
    {
        $remoteJid = trim($remoteJid);
        if ($remoteJid === '' || str_ends_with($remoteJid, '@g.us')) {
            return null;
        }

        if (preg_match('/^(\d{10,15})@(?:s\.whatsapp\.net|c\.us|whatsapp\.net)$/i', $remoteJid, $m) === 1) {
            return $m[1];
        }

        // Some gateways send only digits before @
        if (preg_match('/^(\d{10,15})@/i', $remoteJid, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Nigerian E.164 digits → 11-digit local form starting with 0 (Mevon / bank APIs).
     * Returns null if the number is not a Nigerian mobile in a known shape.
     */
    public static function e164DigitsToNgLocal11(string $e164Digits): ?string
    {
        $d = self::digitsOnly($e164Digits);
        if ($d === null) {
            return null;
        }
        if (strlen($d) === 13 && str_starts_with($d, '234')) {
            return '0'.substr($d, 3);
        }
        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            return $d;
        }
        if (strlen($d) === 10 && $d[0] !== '0') {
            return '0'.$d;
        }

        return null;
    }

    /**
     * Message is only a phone number (no letters): “paste NG mobile” P2P shortcut from root / services.
     * Nigerian numbers only — use *4* in the wallet menu for other countries (see {@see canonicalInternationalWalletRecipientDigits}).
     */
    public static function parseBareNigerianMobileForP2pShortcut(string $text): ?string
    {
        $raw = trim($text);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/[A-Za-z]/', $raw) === 1) {
            return null;
        }
        $compact = preg_replace('/\s+/', '', $raw) ?? '';
        if (preg_match('/^\+?[\d().\-]+$/', $compact) !== 1) {
            return null;
        }

        return self::canonicalNgE164Digits($raw);
    }

    /**
     * Phone-only shortcut parser across supported wallet countries.
     */
    public static function parseBareWalletMobileForP2pShortcut(string $text, ?string $senderPhoneE164): ?string
    {
        $raw = trim($text);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/[A-Za-z]/', $raw) === 1) {
            return null;
        }
        $compact = preg_replace('/\s+/', '', $raw) ?? '';
        if (preg_match('/^\+?[\d().\-]+$/', $compact) !== 1) {
            return null;
        }

        return self::canonicalWalletRecipientForSender($raw, $senderPhoneE164);
    }

    private static function looksExplicitInternational(string $digits): bool
    {
        return str_starts_with($digits, '260')
            || str_starts_with($digits, '263')
            || str_starts_with($digits, '264')
            || str_starts_with($digits, '267')
            || str_starts_with($digits, '255')
            || str_starts_with($digits, '234')
            || str_starts_with($digits, '233')
            || str_starts_with($digits, '229')
            || str_starts_with($digits, '44')
            || (str_starts_with($digits, '1') && strlen($digits) === 11)
            || (str_starts_with($digits, '27') && strlen($digits) >= 11);
    }

    private static function countryFromE164(string $senderPhoneE164): ?string
    {
        $d = self::digitsOnly($senderPhoneE164);
        if ($d === null) {
            return null;
        }

        if (str_starts_with($d, '260')) {
            return 'ZM';
        }
        if (str_starts_with($d, '263')) {
            return 'ZW';
        }
        if (str_starts_with($d, '264')) {
            return 'NA';
        }
        if (str_starts_with($d, '267')) {
            return 'BW';
        }
        if (str_starts_with($d, '255')) {
            return 'TZ';
        }
        if (str_starts_with($d, '234')) {
            return 'NG';
        }
        if (str_starts_with($d, '233')) {
            return 'GH';
        }
        if (str_starts_with($d, '229')) {
            return 'BJ';
        }
        if (str_starts_with($d, '27') && strlen($d) === 11) {
            return 'ZA';
        }
        if (str_starts_with($d, '44')) {
            return 'GB';
        }
        if (str_starts_with($d, '1') && strlen($d) === 11) {
            return 'US';
        }

        return null;
    }
}
