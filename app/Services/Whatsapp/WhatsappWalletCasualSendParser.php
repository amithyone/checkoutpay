<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Services\WhatsappWalletBankPayoutService;

/**
 * Parses short natural-language bank / P2P send intents from the wallet submenu.
 *
 * Text is normalized (strip *bold*, full-width digits) before matching. Supports
 * "send 5k to Name Bank", "pay 2000 for name opay", "transfer 5k name opay" (no "to"),
 * and "send 5k to 080…" for P2P. Bank repeat uses recent outbound transfers.
 */
final class WhatsappWalletCasualSendParser
{
    private const MIN_AMOUNT = 1.0;

    /** Strip WhatsApp formatting so regexes match reliably. */
    public static function normalizeForCasualParse(string $text): string
    {
        $t = trim($text);
        $t = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $t) ?? $t;
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

        return (bool) preg_match('/\d.*\b(?:to|for)\b|[a-z].*\b(?:to|for)\b.*\d/is', $t);
    }

    /** Largest Naira amount found (for deciding whether to show a parse hint). */
    public static function largestNairaAmount(string $text): ?float
    {
        return self::pickLargestAmount($text);
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
        $normalized = self::normalizeForCasualParse($text);
        if (! self::looksLikeCasualSend($normalized)) {
            return null;
        }

        $amount = self::pickLargestAmount($normalized);
        if ($amount === null || $amount < self::MIN_AMOUNT) {
            return null;
        }

        $senderE164 = PhoneNormalizer::canonicalNgE164Digits(
            PhoneNormalizer::digitsOnly($wallet->phone_e164) ?? $wallet->phone_e164
        );
        $recipientPhone = self::extractNigerianPhoneE164($normalized);
        if ($recipientPhone !== null && $recipientPhone !== $senderE164) {
            return ['flow' => 'p2p', 'amount' => $amount, 'recipient_e164' => $recipientPhone];
        }

        $bankMatch = self::extractBankAndNameClause($normalized, $bankPayout);
        if ($bankMatch === null) {
            if (count($recentBank) === 1) {
                $hit = self::matchRecentBankTarget($recentBank, '', null);
                if ($hit !== null) {
                    return [
                        'flow' => 'bank',
                        'amount' => $amount,
                        'ctx' => [
                            'dest_acct' => $hit['acct'],
                            'dest_bank_code' => $hit['bank_code'],
                            'dest_bank' => $hit['bank_name'],
                            'dest_acct_name' => $hit['account_name'],
                            'amount' => $amount,
                        ],
                    ];
                }
            }

            return null;
        }

        [$nameNeedle, $resolvedBank] = $bankMatch;
        $nameNeedle = trim($nameNeedle);

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
        if (preg_match_all('/\b(\d+)\s*k\b/iu', $text, $m)) {
            foreach ($m[1] as $x) {
                $amounts[] = (float) $x * 1000;
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
        if (preg_match_all('/\b(\d{2,}(?:\.\d{1,2})?)\b/', $text, $m)) {
            foreach ($m[1] as $x) {
                $digits = preg_replace('/\D+/', '', $x) ?? '';
                if (self::digitsLookLikeNgPhone($digits)) {
                    continue;
                }
                $n = (float) str_replace([',', ' '], '', $x);
                if ($n >= self::MIN_AMOUNT) {
                    $amounts[] = $n;
                }
            }
        }

        return $amounts === [] ? null : max($amounts);
    }

    private static function digitsLookLikeNgPhone(string $digits): bool
    {
        if ($digits === '') {
            return false;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return true;
        }
        if (strlen($digits) === 13 && str_starts_with($digits, '234')) {
            return true;
        }
        if (strlen($digits) === 10 && $digits[0] !== '0') {
            return true;
        }

        return false;
    }

    private static function extractNigerianPhoneE164(string $text): ?string
    {
        $digitsOnly = preg_replace('/\D+/', '', $text) ?? '';
        if ($digitsOnly !== '' && preg_match_all('/(?:234\d{10}|0\d{10})/', $digitsOnly, $m)) {
            foreach ($m[0] as $chunk) {
                $c = PhoneNormalizer::canonicalNgE164Digits($chunk);
                if ($c !== null && strlen($c) === 13 && str_starts_with($c, '234')) {
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
                $c = PhoneNormalizer::canonicalNgE164Digits($d);
                if ($c !== null && strlen($c) === 13 && str_starts_with($c, '234')) {
                    return $c;
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: array{code: string, name: string}|null}|null  [nameNeedle, resolvedBank|null]
     */
    private static function extractBankAndNameClause(string $text, WhatsappWalletBankPayoutService $bankPayout): ?array
    {
        $clause = null;
        if (preg_match('/\b(?:to|for)\s+(.+)$/is', $text, $m)) {
            $clause = trim($m[1]);
        }
        if ($clause === null || $clause === '') {
            $clause = self::clauseAfterIntentAndAmount($text);
        }
        if ($clause === null || $clause === '') {
            return null;
        }

        if (self::extractNigerianPhoneE164($clause) !== null) {
            $letters = preg_replace('/[\d\s\+\-()]/u', '', $clause) ?? '';
            if ($letters === '' || ! preg_match('/[a-z]/i', $letters)) {
                return null;
            }
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
     * "send 5k innocent opay" → "innocent opay" after stripping verb + amount.
     */
    private static function clauseAfterIntentAndAmount(string $t): ?string
    {
        $t = trim($t);
        if (! preg_match('/\b(send|transfer|pay|move|give|sending)\b/iu', $t)) {
            return null;
        }
        $rest = preg_replace('/^\s*(send|transfer|pay|move|give|sending)\s+/iu', '', $t) ?? $t;
        $rest = trim($rest);
        if ($rest === '') {
            return null;
        }
        $before = $rest;
        $patterns = [
            '/^\s*₦\s*(\d+(?:[,\s]\d{3})*(?:\.\d{1,2})?)\s*/u',
            '/^\s*(\d+(?:[,\s]\d{3})*(?:\.\d{1,2})?)\s*k\b/iu',
            '/^\s*(\d+)\s*k\b/iu',
            '/^\s*(\d+(?:[,\s]\d{3})*(?:\.\d{1,2})?)\s*m\b/iu',
            '/^\s*(\d{2,}(?:\.\d{1,2})?)\s*/',
        ];
        foreach ($patterns as $p) {
            $new = preg_replace($p, '', $rest, 1);
            if ($new !== null && trim($new) !== '' && $new !== $rest) {
                $rest = trim($new);

                break;
            }
        }
        $rest = trim($rest);
        if ($rest === '' || $rest === $before) {
            return null;
        }
        $rest = preg_replace('/^(?:to|for)\s+/iu', '', $rest) ?? $rest;

        return trim($rest) !== '' ? trim($rest) : null;
    }

    /**
     * @param  list<array{acct: string, bank_code: string, bank_name: string, account_name: string}>  $recent
     * @param  array{code: string, name: string}|null  $bankFilter
     */
    private static function matchRecentBankTarget(array $recent, string $nameNeedle, ?array $bankFilter): ?array
    {
        if ($recent === []) {
            return null;
        }

        $needle = strtolower(trim($nameNeedle));

        if ($needle === '') {
            return self::matchWhenNoNameTokens($recent, $bankFilter);
        }

        $hits = [];
        foreach ($recent as $r) {
            $nm = strtolower(trim($r['account_name']));
            $matchedName = $nm !== '' && str_contains($nm, $needle);
            if (! $matchedName && $nm !== '') {
                $toks = preg_split('/\s+/u', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($toks as $tok) {
                    if (strlen($tok) >= 2 && str_contains($nm, strtolower($tok))) {
                        $matchedName = true;

                        break;
                    }
                }
            }
            if (! $matchedName) {
                continue;
            }
            if (! self::rowMatchesBankFilter($r, $bankFilter)) {
                continue;
            }
            $hits[] = $r;
        }

        if (count($hits) === 1) {
            return $hits[0];
        }

        return null;
    }

    /**
     * @param  array{acct: string, bank_code: string, bank_name: string, account_name: string}  $r
     * @param  array{code: string, name: string}|null  $bankFilter
     */
    private static function rowMatchesBankFilter(array $r, ?array $bankFilter): bool
    {
        if ($bankFilter === null) {
            return true;
        }
        $codeOk = strcasecmp((string) $r['bank_code'], (string) $bankFilter['code']) === 0;
        $nameOk = stripos((string) $r['bank_name'], (string) $bankFilter['name']) !== false
            || stripos((string) $bankFilter['name'], (string) $r['bank_name']) !== false;

        return $codeOk || $nameOk;
    }

    /**
     * @param  list<array{acct: string, bank_code: string, bank_name: string, account_name: string}>  $recent
     * @param  array{code: string, name: string}|null  $bankFilter
     */
    private static function matchWhenNoNameTokens(array $recent, ?array $bankFilter): ?array
    {
        if (count($recent) !== 1) {
            return null;
        }
        $only = $recent[0];
        if (! self::rowMatchesBankFilter($only, $bankFilter)) {
            return null;
        }

        return $only;
    }
}
