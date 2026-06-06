<?php

namespace App\Services\Consumer;

use App\Models\Setting;
use App\Models\WhatsappCrossBorderFxRate;
use App\Services\MevonPay\MevonPayExchangeRateService;
use App\Services\Whatsapp\WhatsappCrossBorderP2pFxService;

final class VirtualCardFxService
{
    private bool $midUsdNgnRateComputed = false;

    private ?float $midUsdNgnRateCache = null;

    private bool $sellRateComputed = false;

    private ?float $sellRateCache = null;

    private bool $buyRateComputed = false;

    private ?float $buyRateCache = null;

    public function __construct(
        private WhatsappCrossBorderP2pFxService $crossBorderFx,
        private MevonPayExchangeRateService $mevonRate,
    ) {}

    public function isMidAutoSyncEnabled(): bool
    {
        $stored = Setting::get('virtual_card_fx_mid_auto_sync');
        if ($stored !== null) {
            return (bool) $stored;
        }

        return (bool) config('virtual_card.fx_mid_auto_sync', true);
    }

    public function mevonLiveMidRate(): ?float
    {
        $live = $this->mevonRate->ngnPerUsd();

        return ($live !== null && $live > 0) ? round($live, 4) : null;
    }

    public function publishedMidUsdNgnRate(): ?float
    {
        $stored = Setting::get('virtual_card_fx_published_mid');
        if ($stored !== null && is_numeric($stored) && (float) $stored > 0) {
            return round((float) $stored, 4);
        }

        return null;
    }

    public function publishedSellRate(): ?float
    {
        $stored = Setting::get('virtual_card_fx_published_sell_rate');
        if ($stored !== null && is_numeric($stored) && (float) $stored > 0) {
            return round((float) $stored, 4);
        }

        return null;
    }

    public function publishedBuyRate(): ?float
    {
        $stored = Setting::get('virtual_card_fx_published_buy_rate');
        if ($stored !== null && is_numeric($stored) && (float) $stored > 0) {
            return round((float) $stored, 4);
        }

        return null;
    }

    public function publishedAt(): ?string
    {
        $at = Setting::get('virtual_card_fx_published_at');

        return is_string($at) && $at !== '' ? $at : null;
    }

    public function manualMidUsdNgnRate(): ?float
    {
        $stored = Setting::get('virtual_card_fx_mid_usd_ngn');
        if ($stored !== null && is_numeric($stored) && (float) $stored > 0) {
            return round((float) $stored, 4);
        }

        $fromConfig = config('virtual_card.fx_mid_usd_ngn');
        if ($fromConfig !== null && is_numeric($fromConfig) && (float) $fromConfig > 0) {
            return round((float) $fromConfig, 4);
        }

        return null;
    }

    public function midUsdNgnRate(): ?float
    {
        if ($this->midUsdNgnRateComputed) {
            return $this->midUsdNgnRateCache;
        }

        $this->midUsdNgnRateComputed = true;

        $published = $this->publishedMidUsdNgnRate();
        if ($published !== null) {
            return $this->midUsdNgnRateCache = $published;
        }

        $manual = $this->manualMidUsdNgnRate();
        if ($manual !== null) {
            return $this->midUsdNgnRateCache = $manual;
        }

        $from = (string) config('virtual_card.fee_currency_from', 'USD');
        $to = (string) config('virtual_card.fee_currency_to', 'NGN');
        $fallback = $this->crossBorderFx->convertCurrency($from, $to, 1.0);
        if ($fallback !== null && $fallback > 0) {
            return $this->midUsdNgnRateCache = round($fallback, 4);
        }

        $row = WhatsappCrossBorderFxRate::query()
            ->where('from_currency', 'USD')
            ->where('to_currency', 'NGN')
            ->first();
        if ($row && (float) $row->rate > 0) {
            return $this->midUsdNgnRateCache = round((float) $row->rate, 4);
        }

        return $this->midUsdNgnRateCache = null;
    }

    public function midSource(): string
    {
        $publishedSource = Setting::get('virtual_card_fx_published_source');
        if ($this->publishedMidUsdNgnRate() !== null) {
            return is_string($publishedSource) && $publishedSource !== ''
                ? 'published_'.$publishedSource
                : 'admin_published';
        }

        if ($this->manualMidUsdNgnRate() !== null) {
            return 'manual';
        }

        $from = (string) config('virtual_card.fee_currency_from', 'USD');
        $to = (string) config('virtual_card.fee_currency_to', 'NGN');
        $fallback = $this->crossBorderFx->convertCurrency($from, $to, 1.0);
        if ($fallback !== null && $fallback > 0) {
            return 'cross_border';
        }

        $row = WhatsappCrossBorderFxRate::query()
            ->where('from_currency', 'USD')
            ->where('to_currency', 'NGN')
            ->first();
        if ($row && (float) $row->rate > 0) {
            return 'fx_table';
        }

        return 'unavailable';
    }

    /**
     * Fixed NGN profit per $1 when user funds card (sell side).
     */
    public function sellProfitNgnPerUsd(): float
    {
        $stored = Setting::get('virtual_card_fx_sell_profit_ngn');
        if ($stored !== null && is_numeric($stored)) {
            return max(0.0, round((float) $stored, 2));
        }

        $legacyPercent = Setting::get('virtual_card_fx_sell_markup_percent');
        $mid = $this->midUsdNgnRate();
        if ($legacyPercent !== null && is_numeric($legacyPercent) && $mid !== null && $mid > 0) {
            return max(0.0, round($mid * ((float) $legacyPercent / 100), 2));
        }

        return max(0.0, round((float) config('virtual_card.fx_sell_profit_ngn', 0), 2));
    }

    /**
     * Fixed NGN profit per $1 when user withdraws from card (buy side).
     */
    public function buyProfitNgnPerUsd(): float
    {
        $stored = Setting::get('virtual_card_fx_buy_profit_ngn');
        if ($stored !== null && is_numeric($stored)) {
            return max(0.0, round((float) $stored, 2));
        }

        $legacyPercent = Setting::get('virtual_card_fx_buy_markup_percent');
        $mid = $this->midUsdNgnRate();
        if ($legacyPercent !== null && is_numeric($legacyPercent) && $mid !== null && $mid > 0) {
            return max(0.0, round($mid * ((float) $legacyPercent / 100), 2));
        }

        return max(0.0, round((float) config('virtual_card.fx_buy_profit_ngn', 0), 2));
    }

    public function sellRate(): ?float
    {
        if ($this->sellRateComputed) {
            return $this->sellRateCache;
        }

        $this->sellRateComputed = true;

        $explicit = Setting::get('virtual_card_fx_sell_rate');
        if ($explicit !== null && is_numeric($explicit) && (float) $explicit > 0) {
            return $this->sellRateCache = round((float) $explicit, 4);
        }

        $publishedSell = $this->publishedSellRate();
        if ($publishedSell !== null) {
            return $this->sellRateCache = $publishedSell;
        }

        $mid = $this->midUsdNgnRate();
        if ($mid === null) {
            return $this->sellRateCache = null;
        }

        return $this->sellRateCache = round($mid + $this->sellProfitNgnPerUsd(), 4);
    }

    public function buyRate(): ?float
    {
        if ($this->buyRateComputed) {
            return $this->buyRateCache;
        }

        $this->buyRateComputed = true;

        $explicit = Setting::get('virtual_card_fx_buy_rate');
        if ($explicit !== null && is_numeric($explicit) && (float) $explicit > 0) {
            return $this->buyRateCache = round((float) $explicit, 4);
        }

        $publishedBuy = $this->publishedBuyRate();
        if ($publishedBuy !== null) {
            return $this->buyRateCache = $publishedBuy;
        }

        $mid = $this->midUsdNgnRate();
        if ($mid === null) {
            return $this->buyRateCache = null;
        }

        $rate = round($mid - $this->buyProfitNgnPerUsd(), 4);
        if ($rate <= 0) {
            return $this->buyRateCache = null;
        }

        return $this->buyRateCache = $rate;
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
            'fx_mid_auto_sync' => $this->isMidAutoSyncEnabled(),
            'fx_mid_source' => $this->midSource(),
            'fx_published_at' => $this->publishedAt(),
            'sell_rate' => $this->sellRate(),
            'buy_rate' => $this->buyRate(),
            'sell_profit_ngn_per_usd' => $this->sellProfitNgnPerUsd(),
            'buy_profit_ngn_per_usd' => $this->buyProfitNgnPerUsd(),
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

}
