<?php

namespace App\Services\Whatsapp;

/**
 * Strips common WhatsApp formatting and full-width digits so *1* and １ match menu commands.
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

        return $t;
    }
}
