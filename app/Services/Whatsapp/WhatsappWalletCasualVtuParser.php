<?php

namespace App\Services\Whatsapp;

/**
 * Natural-language bill intents (airtime / data / electricity) from the wallet submenu or guest menu.
 */
final class WhatsappWalletCasualVtuParser
{
    private const MIN_AMOUNT = 50.0;

    /**
     * Single-word or short command → bill flow entry.
     *
     * @return 'airtime'|'data'|'electricity'|'bills'|null
     */
    public static function shortcutKind(string $cmdUpper): ?string
    {
        $u = strtoupper(trim($cmdUpper));
        if ($u === '') {
            return null;
        }

        if (in_array($u, ['5', 'VTU', 'BILLS', 'BILL', 'PAY BILLS', 'PAYBILLS'], true)) {
            return 'bills';
        }
        if (in_array($u, ['AIRTIME', 'RECHARGE', 'TOPUP', 'TOP UP'], true)) {
            return 'airtime';
        }
        if (in_array($u, ['DATA', 'BUNDLE', 'DATABUNDLE'], true)) {
            return 'data';
        }
        if (in_array($u, ['POWER', 'ELECTRICITY', 'ELECTRIC', 'NEPA', 'PHCN', 'PREPAID', 'POSTPAID'], true)) {
            return 'electricity';
        }

        return null;
    }

    public static function looksLikeCasualBill(string $text): bool
    {
        if (self::shortcutKind(strtoupper(trim($text))) !== null) {
            return true;
        }

        $t = mb_strtolower(trim($text));
        if ($t === '') {
            return false;
        }

        if (preg_match('/\b(airtime|data\s+bundle|data\s+plan|buy\s+data|data\b|electricity|electric|nepa|phcn|prepaid|postpaid|meter|disco|pay\s+bills?|bills?)\b/u', $t)) {
            return true;
        }
        if (preg_match('/\b(buy|purchase|pay|send|get|recharge|top\s*up)\b/u', $t)
            && preg_match('/\b(airtime|data|electricity|power|nepa)\b/u', $t)) {
            return true;
        }

        return false;
    }

    /**
     * @return array{
     *   kind: 'airtime'|'data'|'electricity',
     *   network_id?: string,
     *   recipient_e164?: string,
     *   amount?: float,
     *   meter?: string,
     *   disco_service?: string,
     *   meter_type?: 'prepaid'|'postpaid',
     *   data_plan_hint?: string
     * }|null
     */
    public static function tryParse(string $text, string $senderE164): ?array
    {
        $normalized = WhatsappWalletCasualSendParser::normalizeForCasualParse($text);
        if (! self::looksLikeCasualBill($normalized)) {
            return null;
        }

        $kind = self::detectKind($normalized);
        if ($kind === null) {
            return null;
        }

        $out = ['kind' => $kind];

        if ($kind === 'electricity') {
            $meter = self::extractMeterNumber($normalized);
            if ($meter !== null) {
                $out['meter'] = $meter;
            }
            $disco = self::matchDisco($normalized);
            if ($disco !== null) {
                $out['disco_service'] = $disco;
            }
            if (preg_match('/\bpostpaid\b/i', $normalized)) {
                $out['meter_type'] = 'postpaid';
            } elseif (preg_match('/\bprepaid\b/i', $normalized)) {
                $out['meter_type'] = 'prepaid';
            }
        } else {
            $phone = self::extractRecipientPhone($normalized, $senderE164);
            if ($phone !== null) {
                $out['recipient_e164'] = $phone;
            }
            $network = self::matchNetwork($normalized);
            if ($network !== null) {
                $out['network_id'] = $network;
            }
            if ($kind === 'data' && preg_match('/\b(\d+(?:\.\d+)?)\s*(gb|mb)\b/i', $normalized, $dm)) {
                $out['data_plan_hint'] = strtolower($dm[1].$dm[2]);
            }
        }

        $amount = self::pickBillAmount($normalized, $kind, $out['meter'] ?? null, $out['recipient_e164'] ?? null);
        if ($amount !== null) {
            $out['amount'] = $amount;
        }

        if (! self::parseIsActionable($out)) {
            return null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function parseIsActionable(array $parsed): bool
    {
        $kind = (string) ($parsed['kind'] ?? '');
        if ($kind === 'airtime') {
            return isset($parsed['amount']) || isset($parsed['recipient_e164']) || isset($parsed['network_id']);
        }
        if ($kind === 'data') {
            return isset($parsed['network_id']) || isset($parsed['recipient_e164']) || isset($parsed['data_plan_hint']) || isset($parsed['amount']);
        }
        if ($kind === 'electricity') {
            return isset($parsed['meter']) || isset($parsed['amount']) || isset($parsed['disco_service']);
        }

        return false;
    }

    private static function detectKind(string $text): ?string
    {
        $t = mb_strtolower($text);
        if (preg_match('/\b(electricity|electric|nepa|phcn|meter|disco|ikedc|ekedc|ibedc|aedc|phed|prepaid|postpaid)\b/u', $t)) {
            return 'electricity';
        }
        if (preg_match('/\b(data|bundle|gb|mb)\b/u', $t) && ! preg_match('/\bairtime\b/u', $t)) {
            return 'data';
        }
        if (preg_match('/\b(airtime|recharge)\b/u', $t)) {
            return 'airtime';
        }
        if (preg_match('/\b(power)\b/u', $t) && preg_match('/\b(pay|buy|send|nepa|meter)\b/u', $t)) {
            return 'electricity';
        }

        return null;
    }

    private static function extractRecipientPhone(string $text, string $senderE164): ?string
    {
        if (preg_match_all('/\b(\d{10,13})\b/', $text, $m)) {
            foreach ($m[1] as $digits) {
                if (self::digitsLookLikeNgMobile($digits)) {
                    $e164 = PhoneNormalizer::canonicalNgE164Digits($digits);
                    if ($e164 !== null) {
                        return $e164;
                    }
                }
            }
        }

        return null;
    }

    private static function extractMeterNumber(string $text): ?string
    {
        if (preg_match('/\b(?:meter|account|no\.?|number)\s*[#:]?\s*(\d{10,13})\b/i', $text, $m)) {
            return $m[1];
        }
        if (preg_match_all('/\b(\d{11})\b/', $text, $m)) {
            foreach ($m[1] as $digits) {
                if (! self::digitsLookLikeNgMobile($digits)) {
                    return $digits;
                }
            }
        }

        return null;
    }

    private static function digitsLookLikeNgMobile(string $digits): bool
    {
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

    private static function matchNetwork(string $text): ?string
    {
        $t = mb_strtolower($text);
        $aliases = [
            'mtn' => 'mtn',
            'glo' => 'glo',
            'airtel' => 'airtel',
            '9mobile' => '9mobile',
            'etisalat' => '9mobile',
            '9 mobile' => '9mobile',
        ];
        foreach ($aliases as $needle => $id) {
            if (str_contains($t, $needle)) {
                return $id;
            }
        }
        $nets = config('vtu.networks', []);
        if (is_array($nets)) {
            foreach ($nets as $n) {
                if (! is_array($n) || empty($n['id'])) {
                    continue;
                }
                $label = mb_strtolower((string) ($n['label'] ?? ''));
                if ($label !== '' && str_contains($t, $label)) {
                    return (string) $n['id'];
                }
            }
        }

        return null;
    }

    private static function matchDisco(string $text): ?string
    {
        $t = mb_strtolower($text);
        $discos = config('vtu.electricity_discos', []);
        if (! is_array($discos)) {
            return null;
        }
        $best = null;
        $bestLen = 0;
        foreach ($discos as $d) {
            if (! is_array($d) || empty($d['id'])) {
                continue;
            }
            $label = mb_strtolower((string) ($d['label'] ?? ''));
            $id = (string) $d['id'];
            foreach ([$label, str_replace('-', ' ', $id), $id] as $needle) {
                if ($needle !== '' && str_contains($t, $needle) && strlen($needle) > $bestLen) {
                    $best = $id;
                    $bestLen = strlen($needle);
                }
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function pickBillAmount(string $text, string $kind, ?string $meter, ?string $phoneE164): ?float
    {
        $exclude = [];
        if ($meter !== null) {
            $exclude[] = preg_replace('/\D/', '', $meter) ?? '';
        }
        if ($phoneE164 !== null) {
            $exclude[] = preg_replace('/\D/', '', $phoneE164) ?? '';
        }

        $amounts = [];
        if (preg_match_all('/\b(\d+(?:[,\s]\d{3})*(?:\.\d{1,2})?)\s*k\b/iu', $text, $m)) {
            foreach ($m[1] as $x) {
                $amounts[] = (float) str_replace([',', ' '], '', $x) * 1000;
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
                if (self::digitsLookLikeNgMobile($digits)) {
                    continue;
                }
                foreach ($exclude as $ex) {
                    if ($ex !== '' && str_contains($digits, $ex)) {
                        continue 2;
                    }
                }
                $n = (float) str_replace([',', ' '], '', $x);
                if ($n >= self::MIN_AMOUNT) {
                    $amounts[] = $n;
                }
            }
        }

        return $amounts === [] ? null : max($amounts);
    }
}
