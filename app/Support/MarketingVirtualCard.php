<?php

namespace App\Support;

use App\Services\Consumer\ConsumerVirtualCardService;
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

        $fx = app(VirtualCardFxService::class);
        $sellRate = $fx->sellRate();
        $buyRate = $fx->buyRate();
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
            'published_at' => $fx->publishedAt(),
        ];
    }
}
