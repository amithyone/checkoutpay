<?php

namespace App\Services\Consumer;

use App\Models\Setting;
use App\Models\WhatsappCrossBorderFxRate;
use App\Services\Whatsapp\WhatsappCrossBorderP2pFxService;

final class VirtualCardFxService
{
    public function __construct(
        private WhatsappCrossBorderP2pFxService $crossBorderFx,
    ) {}

    public function midUsdNgnRate(): ?float
    {
        $stored = Setting::get('virtual_card_fx_mid_usd_ngn');
        if ($stored !== null && is_numeric($stored) && (float) $stored > 0) {
            return round((float) $stored, 4);
        }

        $from = (string) config('virtual_card.fee_currency_from', 'USD');
        $to = (string) config('virtual_card.fee_currency_to', 'NGN');
        $fallback = $this->crossBorderFx->convertCurrency($from, $to, 1.0);
        if ($fallback !== null && $fallback > 0) {
            return round($fallback, 4);
        }

        $row = WhatsappCrossBorderFxRate::query()
            ->where('from_currency', 'USD')
            ->where('to_currency', 'NGN')
            ->first();
        if ($row && (float) $row->rate > 0) {
            return round((float) $row->rate, 4);
        }

        return null;
    }

    public function sellMarkupPercent(): float
    {
        return $this->percentSetting('virtual_card_fx_sell_markup_percent', 'virtual_card.fx_sell_markup_percent', 0.0);
    }

    public function buyMarkupPercent(): float
    {
        return $this->percentSetting('virtual_card_fx_buy_markup_percent', 'virtual_card.fx_buy_markup_percent', 0.0);
    }

    public function sellRate(): ?float
    {
        $explicit = Setting::get('virtual_card_fx_sell_rate');
        if ($explicit !== null && is_numeric($explicit) && (float) $explicit > 0) {
            return round((float) $explicit, 4);
        }

        $mid = $this->midUsdNgnRate();
        if ($mid === null) {
            return null;
        }

        return round($mid * (1 + ($this->sellMarkupPercent() / 100)), 4);
    }

    public function buyRate(): ?float
    {
        $explicit = Setting::get('virtual_card_fx_buy_rate');
        if ($explicit !== null && is_numeric($explicit) && (float) $explicit > 0) {
            return round((float) $explicit, 4);
        }

        $mid = $this->midUsdNgnRate();
        if ($mid === null) {
            return null;
        }

        $factor = 1 - ($this->buyMarkupPercent() / 100);
        if ($factor <= 0) {
            return null;
        }

        return round($mid * $factor, 4);
    }

    public function isAvailable(): bool
    {
        return $this->sellRate() !== null && $this->buyRate() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function ratesPayload(): array
    {
        return [
            'fx_available' => $this->isAvailable(),
            'fx_mid_usd_ngn' => $this->midUsdNgnRate(),
            'sell_rate' => $this->sellRate(),
            'buy_rate' => $this->buyRate(),
            'sell_markup_percent' => $this->sellMarkupPercent(),
            'buy_markup_percent' => $this->buyMarkupPercent(),
        ];
    }

    /**
     * @return array{amount_usd: float, amount_ngn: float, fx_mid_usd_ngn: ?float, sell_rate: float, fx_side: string}|null
     */
    public function quoteTopupNgn(float $amountUsd): ?array
    {
        $sell = $this->sellRate();
        if ($sell === null || $amountUsd < 0.01) {
            return null;
        }

        return [
            'amount_usd' => round($amountUsd, 2),
            'amount_ngn' => round($amountUsd * $sell, 2),
            'fx_mid_usd_ngn' => $this->midUsdNgnRate(),
            'sell_rate' => $sell,
            'fx_side' => 'sell',
        ];
    }

    /**
     * @return array{amount_usd: float, amount_ngn: float, fx_mid_usd_ngn: ?float, buy_rate: float, fx_side: string}|null
     */
    public function quoteWithdrawNgn(float $amountUsd): ?array
    {
        $buy = $this->buyRate();
        if ($buy === null || $amountUsd < 0.01) {
            return null;
        }

        return [
            'amount_usd' => round($amountUsd, 2),
            'amount_ngn' => round($amountUsd * $buy, 2),
            'fx_mid_usd_ngn' => $this->midUsdNgnRate(),
            'buy_rate' => $buy,
            'fx_side' => 'buy',
        ];
    }

    public function quoteRequestFeeNgn(float $feeUsd): ?float
    {
        $quote = $this->quoteTopupNgn($feeUsd);

        return $quote['amount_ngn'] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function quoteForAction(float $amountUsd, string $action): ?array
    {
        return match ($action) {
            'topup', 'sell' => $this->quoteTopupNgn($amountUsd),
            'withdraw', 'buy' => $this->quoteWithdrawNgn($amountUsd),
            default => null,
        };
    }

    private function percentSetting(string $settingKey, string $configKey, float $default): float
    {
        $stored = Setting::get($settingKey);
        if ($stored !== null && is_numeric($stored)) {
            return max(0.0, (float) $stored);
        }

        return max(0.0, (float) config($configKey, $default));
    }
}
