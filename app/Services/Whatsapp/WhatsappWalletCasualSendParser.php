<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Services\WhatsappWalletBankPayoutService;

/**
 * Parses short natural-language bank / P2P send intents from the wallet menu, e.g.
 * "send 2k to innocent opay", "transfer 5000 to 08012345678", "pay 2500 p2p to 234...".
 */
final class WhatsappWalletCasualSendParser
{
    private const MIN_AMOUNT = 1.0;

    public static function looksLikeCasualSend(string $text): bool
    {
        $t = trim($text);
        if ($t === '') {
            return false;
        }
        if (preg_match('/^\d+$/', $t)) {
            return false;
        }
        if (preg_match('/\b(send|transfer|pay|move|give|sending)\b/i', $t)) {
            return true;
        }
        if (preg_match('/\b(p2p|whatsapp)\b/i', $t)) {
            return true;
        }

        return (bool) preg_match('/\d.*\bto\b|[a-z].*\bto\b.*\d/is', $t);
    }

    /**
     * @param  list<array{acct: string, bank_code: string, bank_name: string, account_name: string}>  $recentBank
     * @return array{flow: 'bank', amount: float, ctx: array<string, mixed>}|array{flow: 'p2p', amount: float, recipient_e164: string}|null
     */
    public static function tryParse(
        string $text,
        WhatsappWallet $wallet,
        WhatsappWalletBankPayoutService $bankPayout,
        array $recentBank,
    ): ?array {
        if (! self::looksLikeCasualSend($text)) {
            return null;
        }

        $amount = self::pickLargestAmount($text);
        if ($amount === null || $amount < self::MIN_AMOUNT) {
            return null;
        }

        $senderE164 = PhoneNormalizer::canonicalNgE164Digits($wallet->phone_e164);
        $recipientPhone = self::extractNigerianPhoneE164($text);
        if ($recipientPhone !== null && $recipientPhone !== $senderE164) {
            return ['flow' => 'p2p', 'amount' => $amount, 'recipient_e164' => $recipientPhone];
        }

        $bankMatch = self::extractBankAndNameClause($text, $bankPayout);
        if ($bankMatch === null) {
            return null;
        }

        [$nameNeedle, $resolvedBank] = $bankMatch;
        $nameNeedle = trim($nameNeedle);
        if (strlen($nameNeedle) < 2) {
            return null;
        }

        $hit = self::matchRecentBankTarget($recentBank, $nameNeedle, $resolvedBank);
        if ($hit === null) {
            return null;
        }

        $ctx = [
            'dest_acct' => $hit['acct'],
            'dest_bank_code' => $hit['bank_code'],
            'dest_bank' => $hit['bank_name'],
            'dest_acct_name' => $hit['account_name'],
            'amount' => $amount,
        ];

        return ['flow' => 'bank', 'amount' => $amount, 'ctx' => $ctx];
    }

    private static function pickLargestAmount(string $text): ?float
    {
        $amounts = [];
        if (preg_match_all('/\b(\d+(?:[,\s]\d{3})*(?:\.\d{1,2})?)\s*k\b/iu', $text, $m)) {
            foreach ($m[1] as $x) {
                $amounts[] = (float) str_replace([',', ' '], '', $x) * 1000;
            }
        }
        if (preg_match_all('/\b(\d+(?:[,\s]\d{3})*(?:\.\d{1,2})?)\s*m\b/iu', $text, $m)) {
            foreach ($m[1] as $x) {
                $amounts[] = (float) str_replace([',', ' '], '', $x) * 1_000_000;
            }
        }
        if (preg_match_all('/₦\s*(\d+(?:[,\s]\d{3})*(?:\.\d{1,2})?)/u', $text, $m)) {
            foreach ($m[1] as $x) {
                $amounts[] = (float) str_replace([',', ' '], '', $x);
            }
        }
        if (preg_match_all('/\b(\d{3,}(?:\.\d{1,2})?)\b/', $text, $m)) {
            foreach ($m[1] as $x) {
                $n = (float) str_replace([',', ' '], '', $x);
                if ($n >= 100) {
                    $amounts[] = $n;
                }
            }
        }

        return $amounts === [] ? null : max($amounts);
    }

    private static function extractNigerianPhoneE164(string $text): ?string
    {
        $candidates = [];
        if (preg_match_all('/(?:\+?\s*234[\s\-]?|0)\d[\d\s\-()]{8,16}\d/u', $text, $m)) {
            $candidates = $m[0];
        }
        foreach ($candidates as $frag) {
            $d = PhoneNormalizer::digitsOnly($frag);
            if ($d === null) {
                continue;
            }
            $c = PhoneNormalizer::canonicalNgE164Digits($d);
            if ($c !== null && strlen($c) === 13 && str_starts_with($c, '234')) {
                return $c;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: array{code: string, name: string}|null}|null  [nameNeedle, resolvedBank|null]
     */
    private static function extractBankAndNameClause(string $text, WhatsappWalletBankPayoutService $bankPayout): ?array
    {
        if (! preg_match('/\bto\s+(.+)$/is', $text, $m)) {
            return null;
        }
        $clause = trim($m[1]);
        if ($clause === '') {
            return null;
        }
        if (self::extractNigerianPhoneE164($clause) !== null && ! preg_match('/[a-z]/i', preg_replace('/[\d\s\+\-()]/', '', $clause))) {
            return null;
        }

        $words = preg_split('/\s+/u', $clause, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return null;
        }

        $resolvedBank = null;
        $nameWords = $words;

        for ($n = min(3, count($words)); $n >= 1; $n--) {
            $tail = implode(' ', array_slice($words, -$n));
            $hit = $bankPayout->resolveBankFromUserInput($tail);
            if ($hit !== null) {
                $resolvedBank = $hit;
                $nameWords = array_slice($words, 0, -$n);

                break;
            }
        }

        $nameNeedle = trim(implode(' ', $nameWords));

        return [$nameNeedle, $resolvedBank];
    }

    /**
     * @param  list<array{acct: string, bank_code: string, bank_name: string, account_name: string}>  $recent
     * @param  array{code: string, name: string}|null  $bankFilter
     */
    private static function matchRecentBankTarget(array $recent, string $nameNeedle, ?array $bankFilter): ?array
    {
        $needle = strtolower(trim($nameNeedle));
        if ($needle === '') {
            return null;
        }

        $hits = [];
        foreach ($recent as $r) {
            $nm = strtolower(trim($r['account_name']));
            if ($nm === '') {
                continue;
            }
            $matchedName = str_contains($nm, $needle);
            if (! $matchedName) {
                $toks = preg_split('/\s+/u', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($toks as $tok) {
                    if (strlen($tok) >= 3 && str_contains($nm, strtolower($tok))) {
                        $matchedName = true;

                        break;
                    }
                }
            }
            if (! $matchedName) {
                continue;
            }
            if ($bankFilter !== null) {
                $codeOk = strcasecmp((string) $r['bank_code'], (string) $bankFilter['code']) === 0;
                $nameOk = stripos((string) $r['bank_name'], (string) $bankFilter['name']) !== false
                    || stripos((string) $bankFilter['name'], (string) $r['bank_name']) !== false;
                if (! $codeOk && ! $nameOk) {
                    continue;
                }
            }
            $hits[] = $r;
        }

        return count($hits) === 1 ? $hits[0] : null;
    }
}
