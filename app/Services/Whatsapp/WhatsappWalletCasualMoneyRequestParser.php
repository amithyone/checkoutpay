<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;

/**
 * Parses natural-language money request intents from the wallet submenu.
 *
 * Examples: need 5000 from 08031234567, request 5k from 080…, ask 080… for 2000
 */
final class WhatsappWalletCasualMoneyRequestParser
{
    private const MIN_AMOUNT = 1.0;

    public static function normalizeForCasualParse(string $text): string
    {
        return WhatsappWalletCasualSendParser::normalizeForCasualParse($text);
    }

    public static function looksLikeCasualMoneyRequest(string $text): bool
    {
        $t = self::normalizeForCasualParse($text);
        if ($t === '') {
            return false;
        }

        if (preg_match('/\b(need|request|ask)\b/i', $t) && preg_match('/\b(from|for)\b/i', $t)) {
            return true;
        }

        if (preg_match('/\bneed\s+money\b/i', $t)) {
            return true;
        }

        return false;
    }

    /**
     * @return array{amount: float, payer_phone_e164: string}|null
     */
    public static function tryParse(string $text, WhatsappWallet $wallet): ?array
    {
        $normalized = self::normalizeForCasualParse($text);
        if (! self::looksLikeCasualMoneyRequest($normalized)) {
            return null;
        }

        $amount = self::pickLargestAmount($normalized);
        if ($amount === null || $amount < self::MIN_AMOUNT) {
            return null;
        }

        $senderDigits = PhoneNormalizer::digitsOnly($wallet->phone_e164) ?? $wallet->phone_e164;
        $senderE164 = PhoneNormalizer::canonicalInternationalWalletRecipientDigits($senderDigits) ?? $senderDigits;

        $payerPhone = self::extractPayerPhoneE164($normalized, $senderE164);
        if ($payerPhone === null || $payerPhone === $senderE164) {
            $payerPhone = self::extractWalletPhoneFromText($normalized, $senderE164);
        }
        if ($payerPhone === null || $payerPhone === $senderE164) {
            return null;
        }

        return [
            'amount' => $amount,
            'payer_phone_e164' => $payerPhone,
        ];
    }

    private static function pickLargestAmount(string $text): ?float
    {
        return WhatsappWalletCasualSendParser::largestNairaAmount($text);
    }

    private static function extractPayerPhoneE164(string $text, ?string $requesterCanonical): ?string
    {
        if (preg_match('/\bfrom\s+(.+)$/is', $text, $m)) {
            $clause = trim($m[1]);
            $phone = self::phoneFromClause($clause, $requesterCanonical);
            if ($phone !== null) {
                return $phone;
            }
        }

        if (preg_match('/\bfor\s+(.+)$/is', $text, $m)) {
            $clause = trim($m[1]);
            $phone = self::phoneFromClause($clause, $requesterCanonical);
            if ($phone !== null) {
                return $phone;
            }
        }

        return self::phoneFromClause($text, $requesterCanonical);
    }

    private static function phoneFromClause(string $clause, ?string $requesterCanonical): ?string
    {
        $digitsOnly = preg_replace('/\D+/', '', $clause) ?? '';
        if ($digitsOnly !== '' && preg_match_all('/\d{10,16}/', $digitsOnly, $m)) {
            foreach ($m[0] as $chunk) {
                $c = PhoneNormalizer::canonicalInternationalWalletRecipientDigits($chunk)
                    ?? PhoneNormalizer::canonicalNgE164Digits($chunk);
                if ($c !== null && ($requesterCanonical === null || $c !== $requesterCanonical)) {
                    return $c;
                }
            }
        }

        if (preg_match_all('/(?:\+|00)\s*\d[\d\s\-()]{8,18}\d/u', $clause, $m)) {
            foreach ($m[0] as $frag) {
                $d = PhoneNormalizer::digitsOnly($frag);
                if ($d === null) {
                    continue;
                }
                $c = PhoneNormalizer::canonicalInternationalWalletRecipientDigits($d)
                    ?? PhoneNormalizer::canonicalNgE164Digits($d);
                if ($c !== null && ($requesterCanonical === null || $c !== $requesterCanonical)) {
                    return $c;
                }
            }
        }

        $ng = PhoneNormalizer::canonicalNgE164Digits($clause);
        if ($ng !== null && ($requesterCanonical === null || $ng !== $requesterCanonical)) {
            return $ng;
        }

        return null;
    }

    private static function extractWalletPhoneFromText(string $text, ?string $requesterCanonical): ?string
    {
        $digitsOnly = preg_replace('/\D+/', '', $text) ?? '';
        if ($digitsOnly !== '' && preg_match_all('/(?:234\d{10}|0\d{10})/', $digitsOnly, $m)) {
            foreach ($m[0] as $chunk) {
                $c = PhoneNormalizer::canonicalNgE164Digits($chunk)
                    ?? PhoneNormalizer::canonicalInternationalWalletRecipientDigits($chunk);
                if ($c !== null && ($requesterCanonical === null || $c !== $requesterCanonical)) {
                    return $c;
                }
            }
        }

        if (preg_match_all('/(?:\+?\s*234[\s\-]?|0)\d[\d\s\-()]{8,16}\d/u', $text, $m)) {
            foreach ($m[0] as $frag) {
                $d = PhoneNormalizer::digitsOnly($frag);
                if ($d === null) {
                    continue;
                }
                $c = PhoneNormalizer::canonicalNgE164Digits($d)
                    ?? PhoneNormalizer::canonicalInternationalWalletRecipientDigits($d);
                if ($c !== null && ($requesterCanonical === null || $c !== $requesterCanonical)) {
                    return $c;
                }
            }
        }

        return null;
    }
}
