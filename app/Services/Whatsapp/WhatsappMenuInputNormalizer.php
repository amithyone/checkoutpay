<?php

namespace App\Services\Whatsapp;

/**
 * Strips common WhatsApp formatting and full-width digits so *1* and １ match menu commands.
 *
 * Numeric navigation (after stripping): *0* = BACK, *00* = MENU, *000* = RESTART (same as typing the words).
 */
final class WhatsappMenuInputNormalizer
{
    public static function commandToken(string $rawText): string
    {
        $t = strtoupper(trim($rawText));
        $t = preg_replace('/^[\*_~\s]+|[\*_~\s]+$/u', '', $t) ?? $t;
        $t = trim($t);
        for ($i = 0; $i <= 9; $i++) {
            $fw = mb_chr(0xFF10 + $i, 'UTF-8');
            if ($fw !== '' && $fw !== false) {
                $t = str_replace($fw, (string) $i, $t);
            }
        }

        return self::mapNavigationShortcuts($t);
    }

    /**
     * Map digit-only navigation when the whole message is exactly 0, 00, or 000.
     */
    public static function mapNavigationShortcuts(string $cmd): string
    {
        return match ($cmd) {
            '000' => 'RESTART',
            '00' => 'MENU',
            '0' => 'BACK',
            default => $cmd,
        };
    }

    /** One-line hint for footers — numbers first; words still work where handlers map them. */
    public static function navigationHelpFooter(): string
    {
        return '*0* back · *00* menu · *000* restart';
    }
}
