<?php

namespace App\Services\Consumer;

use App\Models\Setting;
use App\Services\MevonPay\MevonPayExchangeRateService;

/**
 * Sync Mevon (or manual) FX into settings so consumer API reads DB only — no live Mevon per user.
 */
final class VirtualCardFxPublishService
{
    public function __construct(
        private MevonPayExchangeRateService $mevonRate,
    ) {}

    /**
     * @return array{ok: bool, message: string, mid?: float, sell_rate?: float, buy_rate?: float, source?: string, live_mevon?: ?float, published_at?: string}
     */
    public function syncFromMevon(): array
    {
        $autoSync = $this->isMidAutoSyncEnabled();
        $liveMevon = $autoSync ? $this->mevonRate->ngnPerUsd() : null;

        $mid = null;
        $source = 'manual';

        if ($liveMevon !== null && $liveMevon > 0) {
            $mid = round($liveMevon, 4);
            $source = 'mevon_live';
        } else {
            $manual = $this->manualMidUsdNgnRate();
            if ($manual !== null) {
                $mid = $manual;
                $source = $autoSync ? 'manual_fallback' : 'manual';
            }
        }

        if ($mid === null || $mid <= 0) {
            return [
                'ok' => false,
                'message' => 'Could not resolve FX mid rate. Configure manual mid or check MevonPay.',
                'live_mevon' => $liveMevon,
            ];
        }

        $sell = $this->resolveSellRate($mid);
        $buy = $this->resolveBuyRate($mid);

        if ($sell === null || $buy === null || $sell <= 0 || $buy <= 0) {
            return [
                'ok' => false,
                'message' => 'Could not compute sell/buy rates from mid and profit settings.',
                'mid' => $mid,
                'live_mevon' => $liveMevon,
            ];
        }

        $publishedAt = now()->toIso8601String();

        Setting::set('virtual_card_fx_published_mid', $mid, 'float', 'virtual_card', 'Published FX mid for CheckoutNow (NGN per 1 USD)');
        Setting::set('virtual_card_fx_published_sell_rate', $sell, 'float', 'virtual_card', 'Published sell rate for CheckoutNow');
        Setting::set('virtual_card_fx_published_buy_rate', $buy, 'float', 'virtual_card', 'Published buy rate for CheckoutNow');
        Setting::set('virtual_card_fx_published_at', $publishedAt, 'string', 'virtual_card', 'When card FX rates were last published for the app');
        Setting::set('virtual_card_fx_published_source', $source, 'string', 'virtual_card', 'Source used when card FX rates were published');

        if ($source === 'mevon_live') {
            Setting::set('virtual_card_fx_mid_usd_ngn', $mid, 'float', 'virtual_card', 'Virtual card FX mid rate (NGN per 1 USD)');
        }

        return [
            'ok' => true,
            'message' => 'Card FX rates published for the app.',
            'mid' => $mid,
            'sell_rate' => $sell,
            'buy_rate' => $buy,
            'source' => $source,
            'live_mevon' => $liveMevon,
            'published_at' => $publishedAt,
        ];
    }

    /**
     * @return array{mid: ?float, sell_rate: ?float, buy_rate: ?float, published_at: ?string, source: ?string}
     */
    public function publishedSnapshot(): array
    {
        return [
            'mid' => $this->readPublishedFloat('virtual_card_fx_published_mid'),
            'sell_rate' => $this->readPublishedFloat('virtual_card_fx_published_sell_rate'),
            'buy_rate' => $this->readPublishedFloat('virtual_card_fx_published_buy_rate'),
            'published_at' => Setting::get('virtual_card_fx_published_at'),
            'source' => Setting::get('virtual_card_fx_published_source'),
        ];
    }

    private function isMidAutoSyncEnabled(): bool
    {
        $stored = Setting::get('virtual_card_fx_mid_auto_sync');
        if ($stored !== null) {
            return (bool) $stored;
        }

        return (bool) config('virtual_card.fx_mid_auto_sync', true);
    }

    private function manualMidUsdNgnRate(): ?float
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

    private function resolveSellRate(float $mid): ?float
    {
        $explicit = Setting::get('virtual_card_fx_sell_rate');
        if ($explicit !== null && is_numeric($explicit) && (float) $explicit > 0) {
            return round((float) $explicit, 4);
        }

        return round($mid + $this->sellProfitNgnPerUsd($mid), 4);
    }

    private function resolveBuyRate(float $mid): ?float
    {
        $explicit = Setting::get('virtual_card_fx_buy_rate');
        if ($explicit !== null && is_numeric($explicit) && (float) $explicit > 0) {
            return round((float) $explicit, 4);
        }

        $rate = round($mid - $this->buyProfitNgnPerUsd($mid), 4);

        return $rate > 0 ? $rate : null;
    }

    private function sellProfitNgnPerUsd(float $mid): float
    {
        $stored = Setting::get('virtual_card_fx_sell_profit_ngn');
        if ($stored !== null && is_numeric($stored)) {
            return max(0.0, round((float) $stored, 2));
        }

        $legacyPercent = Setting::get('virtual_card_fx_sell_markup_percent');
        if ($legacyPercent !== null && is_numeric($legacyPercent) && $mid > 0) {
            return max(0.0, round($mid * ((float) $legacyPercent / 100), 2));
        }

        return max(0.0, round((float) config('virtual_card.fx_sell_profit_ngn', 0), 2));
    }

    private function buyProfitNgnPerUsd(float $mid): float
    {
        $stored = Setting::get('virtual_card_fx_buy_profit_ngn');
        if ($stored !== null && is_numeric($stored)) {
            return max(0.0, round((float) $stored, 2));
        }

        $legacyPercent = Setting::get('virtual_card_fx_buy_markup_percent');
        if ($legacyPercent !== null && is_numeric($legacyPercent) && $mid > 0) {
            return max(0.0, round($mid * ((float) $legacyPercent / 100), 2));
        }

        return max(0.0, round((float) config('virtual_card.fx_buy_profit_ngn', 0), 2));
    }

    private function readPublishedFloat(string $key): ?float
    {
        $value = Setting::get($key);
        if ($value !== null && is_numeric($value) && (float) $value > 0) {
            return round((float) $value, 4);
        }

        return null;
    }
}
