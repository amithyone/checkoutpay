<?php

namespace App\Support;

/**
 * Reject disposable / obscure emails and obvious fake KYC placeholders for WhatsApp wallets.
 */
class WhatsappWalletKycInputGuard
{
    /**
     * @return list<string>
     */
    public static function allowedEmailDomains(): array
    {
        $configured = (array) config('whatsapp.wallet.allowed_email_domains', []);
        $domains = [];
        foreach ($configured as $domain) {
            $domain = strtolower(trim((string) $domain));
            if ($domain !== '') {
                $domains[] = $domain;
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * @return null|string Error message, or null when OK
     */
    public static function emailError(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Enter a valid email address.';
        }

        $domain = substr(strrchr($email, '@') ?: '', 1);
        $domain = strtolower(rtrim($domain, '.'));
        if ($domain === '') {
            return 'Enter a valid email address.';
        }

        $allowed = self::allowedEmailDomains();
        if ($allowed === []) {
            return null;
        }

        foreach ($allowed as $ok) {
            if ($domain === $ok || str_ends_with($domain, '.'.$ok)) {
                return null;
            }
        }

        return 'Use a popular email provider (Gmail, Yahoo, Outlook/Hotmail, iCloud, etc.). Other domains are not allowed for wallet accounts.';
    }

    /**
     * @return null|string Error message, or null when OK
     */
    public static function bvnOrNinError(?string $digits, string $label = 'BVN'): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $digits) ?? '';
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) !== 11) {
            return $label.' must be exactly 11 digits.';
        }
        if (self::digitsLookGeneric($digits)) {
            return $label.' looks invalid or fake. Enter your real '.$label.'.';
        }

        return null;
    }

    /**
     * @return null|string Error message, or null when OK
     */
    public static function cacError(?string $cac): ?string
    {
        $cac = strtoupper(preg_replace('/\s+/', '', trim((string) $cac)) ?? '');
        $cac = preg_replace('/[^A-Z0-9]/', '', $cac) ?? '';
        if ($cac === '') {
            return null;
        }
        if (strlen($cac) < 5 || strlen($cac) > 32) {
            return 'Send a valid CAC / registration number (e.g. RC1234567 or BN1234567).';
        }

        $digits = preg_replace('/\D+/', '', $cac) ?? '';
        if ($digits !== '' && self::digitsLookGeneric($digits)) {
            return 'CAC / registration number looks invalid or fake. Enter the real RC/BN from CAC.';
        }

        // Bare numeric CAC without RC/BN/IT prefix is almost always a probe.
        if (ctype_digit($cac)) {
            return 'Send CAC with prefix (e.g. RC1234567 or BN1234567), not numbers alone.';
        }

        return null;
    }

    /**
     * @return null|string Error message, or null when OK
     */
    public static function phoneError(?string $phoneE164): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phoneE164) ?? '';
        if ($digits === '') {
            return null;
        }

        $national = $digits;
        if (str_starts_with($digits, '234') && strlen($digits) >= 13) {
            $national = substr($digits, 3);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $national = substr($digits, 1);
        }

        if (strlen($national) >= 8 && self::digitsLookGeneric($national)) {
            return 'That mobile number looks invalid or fake. Use your real WhatsApp number.';
        }

        // Classic Nigerian placeholder 8012345678 / 08012345678
        if (in_array($national, ['8012345678', '7012345678', '9012345678', '8111111111', '8000000000'], true)) {
            return 'That mobile number looks invalid or fake. Use your real WhatsApp number.';
        }

        return null;
    }

    /**
     * Obvious placeholders: all same digit, ascending/descending runs, known fakes.
     */
    public static function digitsLookGeneric(string $digits): bool
    {
        $digits = preg_replace('/\D+/', '', $digits) ?? '';
        $len = strlen($digits);
        if ($len < 5) {
            return false;
        }

        if (preg_match('/^(\d)\1+$/', $digits) === 1) {
            return true;
        }

        $denylist = [
            '12345678901',
            '12345678900',
            '01234567890',
            '09876543210',
            '98765432109',
            '1234567890',
            '0123456789',
            '1234567',
            '12345678',
            '123456789',
            '00000000000',
            '11111111111',
            '22222222222',
            '99999999999',
        ];
        if (in_array($digits, $denylist, true)) {
            return true;
        }

        if (self::isSequentialDigits($digits)) {
            return true;
        }

        // Repeating short block: 12121212121, 12312312312
        if ($len >= 8) {
            foreach ([2, 3, 4] as $block) {
                if ($len % $block !== 0) {
                    continue;
                }
                $unit = substr($digits, 0, $block);
                if ($unit !== '' && str_repeat($unit, intdiv($len, $block)) === $digits) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isSequentialDigits(string $digits): bool
    {
        $len = strlen($digits);
        if ($len < 6) {
            return false;
        }

        $asc = true;
        $desc = true;
        for ($i = 1; $i < $len; $i++) {
            $prev = (int) $digits[$i - 1];
            $cur = (int) $digits[$i];
            if ($cur !== (($prev + 1) % 10)) {
                $asc = false;
            }
            if ($cur !== (($prev + 9) % 10)) {
                $desc = false;
            }
            if (! $asc && ! $desc) {
                return false;
            }
        }

        return $asc || $desc;
    }
}
