<?php

namespace App\Services\Whatsapp;

use App\Models\Setting;
use App\Models\WhatsappCrossBorderFxRate;
use Illuminate\Support\Facades\Cache;

/**
 * Cross-border WhatsApp P2P: optional FX when sender and recipient wallet currencies differ.
 * Backward compatible: same currency → no FX; feature off → domestic-only behavior for mixed currency.
 */
final class WhatsappCrossBorderP2pFxService
{
    public const FX_RATES_CACHE_KEY = 'whatsapp_cross_border_fx_rates_map_v1';

    public const RATE_SOURCE_MANUAL = 'manual';

    public const RATE_SOURCE_OPEN_ER_USD = 'open_er_usd';

    public function __construct(
        private WhatsappWalletCountryResolver $countries,
        private WhatsappCrossBorderFxOpenErProvider $openErUsd,
    ) {}

    public static function forgetRatesCache(): void
    {
        Cache::forget(self::FX_RATES_CACHE_KEY);
        WhatsappCrossBorderFxOpenErProvider::forgetCache();
    }

    public function senderCurrencyForInstance(string $evolutionInstance): string
    {
        return $this->countries->currencyForEvolutionInstance($evolutionInstance);
    }

    /**
     * Sender currency for P2P: wallet owner's number (correct when one Evolution instance serves several countries).
     * Falls back to {@see currencyForEvolutionInstance} when phone is empty.
     */
    public function senderCurrencyForWalletPhone(string $evolutionInstance, ?string $senderWalletPhoneE164): string
    {
        $d = preg_replace('/\D+/', '', (string) $senderWalletPhoneE164) ?? '';

        return $d !== ''
            ? $this->countries->currencyForPhoneE164($senderWalletPhoneE164)
            : $this->countries->currencyForEvolutionInstance($evolutionInstance);
    }

    /**
     * @return array{
     *   status: 'domestic'|'ok'|'blocked'|'missing_rate',
     *   sender_currency: string,
     *   recipient_currency: string,
     *   debit: float,
     *   credit: float,
     *   message?: string
     * }
     */
    public function evaluateP2p(
        string $evolutionInstance,
        string $recipientPhoneE164,
        float $debitAmount,
        ?string $senderWalletPhoneE164 = null,
    ): array {
        $senderCur = $this->senderCurrencyForWalletPhone($evolutionInstance, $senderWalletPhoneE164);
        $recvCur = $this->countries->currencyForPhoneE164($recipientPhoneE164);
        $debit = round($debitAmount, 2);

        if ($senderCur === $recvCur) {
            return [
                'status' => 'domestic',
                'sender_currency' => $senderCur,
                'recipient_currency' => $recvCur,
                'debit' => $debit,
                'credit' => $debit,
            ];
        }

        if (! (bool) Setting::get('whatsapp_cross_border_p2p_enabled', false)) {
            return [
                'status' => 'blocked',
                'sender_currency' => $senderCur,
                'recipient_currency' => $recvCur,
                'debit' => $debit,
                'credit' => $debit,
                'message' => $this->defaultOrSettingText(
                    'whatsapp_cross_border_disabled_message',
                    'Cross-border wallet sends are turned off. Ask an admin to enable them or send to someone in the same region.'
                ),
            ];
        }

        $mult = $this->fxMultiplier($senderCur, $recvCur);
        if ($mult === null) {
            return [
                'status' => 'missing_rate',
                'sender_currency' => $senderCur,
                'recipient_currency' => $recvCur,
                'debit' => $debit,
                'credit' => $debit,
                'message' => $this->defaultOrSettingText(
                    'whatsapp_cross_border_missing_rate_message',
                    'We do not have an exchange rate for this pair yet. Ask an admin to add it in WhatsApp wallet settings.'
                ),
            ];
        }

        $credit = round($debit * $mult * $this->recipientSideMarginFactor(), 2);
        if ($credit < 0.01) {
            return [
                'status' => 'missing_rate',
                'sender_currency' => $senderCur,
                'recipient_currency' => $recvCur,
                'debit' => $debit,
                'credit' => $debit,
                'message' => $this->defaultOrSettingText(
                    'whatsapp_cross_border_missing_rate_message',
                    'We do not have an exchange rate for this pair yet. Ask an admin to add it in WhatsApp wallet settings.'
                ),
            ];
        }

        return [
            'status' => 'ok',
            'sender_currency' => $senderCur,
            'recipient_currency' => $recvCur,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    /**
     * Units of TO per 1 FROM (e.g. NGN_NAD = NAD per 1 NGN).
     */
    private function fxMultiplier(string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) {
            return 1.0;
        }

        $source = (string) Setting::get('whatsapp_cross_border_fx_rate_source', self::RATE_SOURCE_MANUAL);
        if ($source === self::RATE_SOURCE_OPEN_ER_USD) {
            $live = $this->openErUsd->multiplier($from, $to);
            if ($live !== null && $live > 0) {
                return $live;
            }
        }

        return $this->manualFxMultiplier($from, $to);
    }

    /**
     * One global margin: recipient gets (100 − p)% of the base converted amount (p = profit %).
     */
    private function recipientSideMarginFactor(): float
    {
        $raw = Setting::get('whatsapp_cross_border_fx_profit_margin_percent', 0);
        if (! is_numeric($raw)) {
            return 1.0;
        }
        $p = (float) $raw;
        if ($p <= 0) {
            return 1.0;
        }
        if ($p >= 100) {
            return 0.0;
        }

        return (100.0 - $p) / 100.0;
    }

    private function manualFxMultiplier(string $from, string $to): ?float
    {
        $rates = $this->ratesMap();

        $k = $from.'_'.$to;
        if (isset($rates[$k])) {
            $v = (float) $rates[$k];

            return $v > 0 ? $v : null;
        }

        $rev = $to.'_'.$from;
        if (isset($rates[$rev])) {
            $v = (float) $rates[$rev];

            return $v > 0 ? 1.0 / $v : null;
        }

        return null;
    }

    /**
     * @return array<string, float> key = FROM_TO (uppercase), value = multiplier
     */
    private function ratesMap(): array
    {
        return Cache::remember(self::FX_RATES_CACHE_KEY, 300, function (): array {
            $map = [];
            foreach (WhatsappCrossBorderFxRate::query()->orderBy('id')->cursor() as $row) {
                $f = strtoupper((string) $row->from_currency);
                $t = strtoupper((string) $row->to_currency);
                $v = (float) $row->rate;
                if ($f !== '' && $t !== '' && $v > 0) {
                    $map[$f.'_'.$t] = $v;
                }
            }
            if ($map !== []) {
                return $map;
            }

            $legacy = Setting::get('whatsapp_cross_border_fx_rates_json', []);
            if (! is_array($legacy)) {
                return [];
            }
            foreach ($legacy as $key => $val) {
                if (! is_string($key) || ! is_numeric($val)) {
                    continue;
                }
                $v = (float) $val;
                if ($v <= 0) {
                    continue;
                }
                $map[strtoupper($key)] = $v;
            }

            return $map;
        });
    }

    private function defaultOrSettingText(string $key, string $fallback): string
    {
        $t = Setting::get($key);
        if (is_string($t) && trim($t) !== '') {
            return trim($t);
        }

        return $fallback;
    }

    public function formatCrossBorderPrompt(array $eval): string
    {
        $tpl = Setting::get('whatsapp_cross_border_prompt_template');
        if (is_string($tpl) && trim($tpl) !== '') {
            return $this->interpolatePrompt(trim($tpl), $eval);
        }

        $debitFmt = WhatsappWalletMoneyFormatter::format((float) $eval['debit'], (string) $eval['sender_currency']);
        $creditFmt = WhatsappWalletMoneyFormatter::format((float) $eval['credit'], (string) $eval['recipient_currency']);

        return "FX: *{$debitFmt}* → *{$creditFmt}* · confirm next.";
    }

    /**
     * @param  array{debit: float, credit: float, sender_currency: string, recipient_currency: string}  $eval
     */
    private function interpolatePrompt(string $tpl, array $eval): string
    {
        $map = [
            '{debit}' => WhatsappWalletMoneyFormatter::format((float) $eval['debit'], (string) $eval['sender_currency']),
            '{credit}' => WhatsappWalletMoneyFormatter::format((float) $eval['credit'], (string) $eval['recipient_currency']),
            '{sender_currency}' => (string) $eval['sender_currency'],
            '{recipient_currency}' => (string) $eval['recipient_currency'],
            '{debit_amount}' => number_format((float) $eval['debit'], 2),
            '{credit_amount}' => number_format((float) $eval['credit'], 2),
        ];

        return strtr($tpl, $map);
    }
}
