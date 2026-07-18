<?php

namespace App\Services\Consumer;

use App\Models\Setting;
use App\Models\WhatsappCrossBorderFxRate;
use App\Services\Cashwyre\CashwyreFxRateService;
use App\Services\MevonPay\MevonPayExchangeRateService;
use App\Services\VirtualCard\VirtualCardProviderResolver;
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
        private CashwyreFxRateService $cashwyreFx,
        private VirtualCardProviderResolver $providerResolver,
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

    public function cashwyreLiveMidRate(): ?float
    {
        $live = $this->cashwyreLiveRates();

        return ($live !== null && ($live['mid'] ?? null) > 0)
            ? round((float) $live['mid'], 4)
            : null;
    }

    public function isCashwyreProvider(): bool
    {
        return $this->providerResolver->activeKey() === VirtualCardProviderResolver::PROVIDER_CASHWYRE;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cashwyreLiveRates(): ?array
    {
        if (! $this->isCashwyreProvider() || ! $this->cashwyreFx->isConfigured()) {
            return null;
        }

        $rates = $this->cashwyreFx->ngnUsdRates();

        return ($rates['ok'] ?? false) ? $rates : null;
    }

    private function publishedSource(): ?string
    {
        $source = Setting::get('virtual_card_fx_published_source');

        return is_string($source) && $source !== '' ? $source : null;
    }

    private function publishedRatesMatchActiveProvider(): bool
    {
        if ($this->isCashwyreProvider()) {
            return $this->publishedSource() === 'cashwyre_live';
        }

        $source = $this->publishedSource();

        return $source === null || $source !== 'cashwyre_live';
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
        if ($published !== null && $this->publishedRatesMatchActiveProvider()) {
            return $this->midUsdNgnRateCache = $published;
        }

        if ($this->isCashwyreProvider()) {
            $liveMid = $this->cashwyreLiveMidRate();
            if ($liveMid !== null) {
                return $this->midUsdNgnRateCache = $liveMid;
            }
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
        if ($this->publishedMidUsdNgnRate() !== null && $this->publishedRatesMatchActiveProvider()) {
            return is_string($publishedSource) && $publishedSource !== ''
                ? 'published_'.$publishedSource
                : 'admin_published';
        }

        if ($this->isCashwyreProvider() && $this->cashwyreLiveMidRate() !== null) {
            return 'cashwyre_live';
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
        if ($publishedSell !== null && $this->publishedRatesMatchActiveProvider()) {
            return $this->sellRateCache = $publishedSell;
        }

        if ($this->isCashwyreProvider()) {
            $live = $this->cashwyreLiveRates();
            $liveSell = is_array($live) ? ($live['sell_rate'] ?? null) : null;
            if ($liveSell !== null && (float) $liveSell > 0) {
                return $this->sellRateCache = round((float) $liveSell + $this->sellProfitNgnPerUsd(), 4);
            }
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
        if ($publishedBuy !== null && $this->publishedRatesMatchActiveProvider()) {
            return $this->buyRateCache = $publishedBuy;
        }

        if ($this->isCashwyreProvider()) {
            $live = $this->cashwyreLiveRates();
            $liveBuy = is_array($live) ? ($live['buy_rate'] ?? null) : null;
            if ($liveBuy !== null && (float) $liveBuy > 0) {
                $rate = round((float) $liveBuy - $this->buyProfitNgnPerUsd(), 4);

                return $this->buyRateCache = $rate > 0 ? $rate : null;
            }
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
        $payload = [
            'fx_available' => $this->isAvailable(),
            'fx_mid_usd_ngn' => $this->midUsdNgnRate(),
            'fx_mid_auto_sync' => $this->isMidAutoSyncEnabled(),
            'fx_mid_source' => $this->midSource(),
            'fx_published_at' => $this->publishedAt(),
            'sell_rate' => $this->sellRate(),
            'buy_rate' => $this->buyRate(),
            'sell_profit_ngn_per_usd' => $this->sellProfitNgnPerUsd(),
            'buy_profit_ngn_per_usd' => $this->buyProfitNgnPerUsd(),
            'card_provider' => $this->providerResolver->activeKey(),
        ];

        if ($this->isCashwyreProvider()) {
            $live = $this->cashwyreLiveRates();
            if ($live !== null) {
                $payload['cashwyre_live_sell_rate'] = $live['sell_rate'] ?? null;
                $payload['cashwyre_live_buy_rate'] = $live['buy_rate'] ?? null;
                $payload['cashwyre_live_mid'] = $live['mid'] ?? null;
                $payload['cashwyre_live_fetched_at'] = $live['fetched_at'] ?? null;
            }
        }

        return $payload;
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

    public function midUsdKesRate(): ?float
    {
        $stored = Setting::get('virtual_card_fx_mid_usd_kes');
        if ($stored !== null && is_numeric($stored) && (float) $stored > 0) {
            return round((float) $stored, 4);
        }
        $cfg = config('virtual_card.fx_mid_usd_kes');
        if ($cfg !== null && is_numeric($cfg) && (float) $cfg > 0) {
            return round((float) $cfg, 4);
        }

        return null;
    }

    public function sellProfitKesPerUsd(): float
    {
        $stored = Setting::get('virtual_card_fx_sell_profit_kes');
        if ($stored !== null && is_numeric($stored)) {
            return max(0.0, round((float) $stored, 2));
        }

        return max(0.0, round((float) config('virtual_card.fx_sell_profit_kes', 5), 2));
    }

    public function buyProfitKesPerUsd(): float
    {
        $stored = Setting::get('virtual_card_fx_buy_profit_kes');
        if ($stored !== null && is_numeric($stored)) {
            return max(0.0, round((float) $stored, 2));
        }

        return max(0.0, round((float) config('virtual_card.fx_buy_profit_kes', 3), 2));
    }

    public function sellRateKes(): ?float
    {
        $explicit = Setting::get('virtual_card_fx_sell_rate_kes');
        if ($explicit !== null && is_numeric($explicit) && (float) $explicit > 0) {
            return round((float) $explicit, 4);
        }
        $cfg = config('virtual_card.fx_sell_rate_kes');
        if ($cfg !== null && is_numeric($cfg) && (float) $cfg > 0) {
            return round((float) $cfg, 4);
        }
        $mid = $this->midUsdKesRate();
        if ($mid === null) {
            return null;
        }

        return round($mid + $this->sellProfitKesPerUsd(), 4);
    }

    public function buyRateKes(): ?float
    {
        $explicit = Setting::get('virtual_card_fx_buy_rate_kes');
        if ($explicit !== null && is_numeric($explicit) && (float) $explicit > 0) {
            return round((float) $explicit, 4);
        }
        $cfg = config('virtual_card.fx_buy_rate_kes');
        if ($cfg !== null && is_numeric($cfg) && (float) $cfg > 0) {
            return round((float) $cfg, 4);
        }
        $mid = $this->midUsdKesRate();
        if ($mid === null) {
            return null;
        }
        $rate = round($mid - $this->buyProfitKesPerUsd(), 4);

        return $rate > 0 ? $rate : null;
    }

    /**
     * Quote card top-up debit in wallet currency. amount_ngn = wallet debit (legacy key).
     *
     * @return array{amount_usd: float, amount_ngn: float, wallet_currency: string, sell_rate: float, fx_side: string, fx_mid?: float|null}|null
     */
    public function quoteTopupForCurrency(float $amountUsd, string $walletCurrency): ?array
    {
        $cur = strtoupper($walletCurrency);
        if ($cur === 'NGN') {
            $q = $this->quoteTopupNgn($amountUsd);
            if ($q === null) {
                return null;
            }
            $q['wallet_currency'] = 'NGN';

            return $q;
        }
        if ($cur === 'KES') {
            $sell = $this->sellRateKes();
            if ($sell === null || $amountUsd < 0.01) {
                return null;
            }

            return [
                'amount_usd' => round($amountUsd, 2),
                'amount_ngn' => round($amountUsd * $sell, 2),
                'wallet_currency' => 'KES',
                'sell_rate' => $sell,
                'fx_side' => 'sell',
                'fx_mid' => $this->midUsdKesRate(),
            ];
        }

        return null;
    }

    /**
     * @return array{amount_usd: float, amount_ngn: float, wallet_currency: string, buy_rate: float, fx_side: string, fx_mid?: float|null}|null
     */
    public function quoteWithdrawForCurrency(float $amountUsd, string $walletCurrency): ?array
    {
        $cur = strtoupper($walletCurrency);
        if ($cur === 'NGN') {
            $q = $this->quoteWithdrawNgn($amountUsd);
            if ($q === null) {
                return null;
            }
            $q['wallet_currency'] = 'NGN';

            return $q;
        }
        if ($cur === 'KES') {
            $buy = $this->buyRateKes();
            if ($buy === null || $amountUsd < 0.01) {
                return null;
            }

            return [
                'amount_usd' => round($amountUsd, 2),
                'amount_ngn' => round($amountUsd * $buy, 2),
                'wallet_currency' => 'KES',
                'buy_rate' => $buy,
                'fx_side' => 'buy',
                'fx_mid' => $this->midUsdKesRate(),
            ];
        }

        return null;
    }

    public function quoteRequestFeeForCurrency(float $feeUsd, string $walletCurrency): ?float
    {
        $quote = $this->quoteTopupForCurrency($feeUsd, $walletCurrency);

        return $quote['amount_ngn'] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function quoteForAction(float $amountUsd, string $action, string $walletCurrency = 'NGN'): ?array
    {
        return match ($action) {
            'topup', 'sell' => $this->quoteTopupForCurrency($amountUsd, $walletCurrency),
            'withdraw', 'buy' => $this->quoteWithdrawForCurrency($amountUsd, $walletCurrency),
            default => null,
        };
    }

}
