<?php

namespace App\Support;

use App\Services\Consumer\ConsumerVirtualCardService;
use App\Services\Consumer\VirtualCardFxPublishService;
use App\Services\Consumer\VirtualCardFxService;

/**
 * Public-facing Dollar Virtual Card rates and fees for marketing pages.
 */
final class MarketingVirtualCard
{
    public static function snapshot(): array
    {
        $cards = app(ConsumerVirtualCardService::class);
        if (! $cards->isEnabled()) {
            return ['enabled' => false];
        }

        $rates = self::appRates();

        $sellRate = $rates['sell_rate'];
        $buyRate = $rates['buy_rate'];
        $setupUsd = $cards->requestFeeUsd();
        $creationUsd = $cards->creationFeeUsd();
        $initialLoadUsd = $cards->initialLoadUsd();

        $setupNgn = ($sellRate !== null && $sellRate > 0)
            ? round($setupUsd * $sellRate, 2)
            : null;

        return [
            'enabled' => true,
            'brand_name' => CheckoutNowApp::brandName(),
            'app_url' => CheckoutNowApp::webUrl(),
            'apk_url' => CheckoutNowApp::androidApkDownloadUrl(),
            'sell_rate' => $sellRate,
            'buy_rate' => $buyRate,
            'sell_rate_label' => $sellRate !== null ? MarketingPricing::formatNaira($sellRate) : null,
            'buy_rate_label' => $buyRate !== null ? MarketingPricing::formatNaira($buyRate) : null,
            'setup_fee_usd' => $setupUsd,
            'creation_fee_usd' => $creationUsd,
            'initial_load_usd' => $initialLoadUsd,
            'setup_fee_ngn' => $setupNgn,
            'setup_fee_ngn_label' => $setupNgn !== null ? MarketingPricing::formatNaira($setupNgn) : null,
            'published_at' => $rates['published_at'],
            'fx_rates_url' => route('virtual-card.fx-rates'),
            'poll_seconds' => $rates['poll_seconds'],
        ];
    }

    /**
     * Published CheckoutNow app FX rates — same source as admin settings and consumer API.
     *
     * @return array{ok: bool, sell_rate: ?float, buy_rate: ?float, mid: ?float, published_at: ?string, updated_at: string, poll_seconds: int}
     */
    public static function appRates(bool $fetchFresh = false): array
    {
        $publish = app(VirtualCardFxPublishService::class);
        $published = $publish->publishedSnapshot();

        if ($published['sell_rate'] === null || $published['buy_rate'] === null) {
            $publish->syncFromMevon();
        } elseif ($fetchFresh) {
            $publish->syncFromMevon();
        }

        $fx = app(VirtualCardFxService::class);

        return [
            'ok' => true,
            'sell_rate' => $fx->sellRate(),
            'buy_rate' => $fx->buyRate(),
            'mid' => $fx->midUsdNgnRate(),
            'published_at' => $fx->publishedAt(),
            'updated_at' => now()->toIso8601String(),
            'poll_seconds' => 60,
        ];
    }
}
