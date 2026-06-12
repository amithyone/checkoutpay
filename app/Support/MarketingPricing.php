<?php

namespace App\Support;

use App\Services\ChargeService;

/**
 * Live gateway pricing for public marketing pages (home, pricing fallbacks).
 */
final class MarketingPricing
{
    public static function snapshot(): array
    {
        $charges = app(ChargeService::class);
        $percentage = $charges->getChargePercentage();
        $fixed = $charges->getChargeFixed();

        return [
            'percentage' => $percentage,
            'fixed' => $fixed,
            'rate_percentage' => self::formatPercentage($percentage),
            'rate_fixed' => self::formatNaira($fixed),
            'rate_description' => 'per successful transaction',
            'pricing_text' => self::formatPercentage($percentage).' + '.self::formatNaira($fixed).' per transaction',
            'examples' => self::examples($percentage, $fixed),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function mergeIntoHomeContent(array $content, ?array $pricing = null): array
    {
        $pricing ??= self::snapshot();

        if (isset($content['hero']) && is_array($content['hero'])) {
            $content['hero']['pricing_text'] = $pricing['pricing_text'];
        }

        if (isset($content['pricing_section']) && is_array($content['pricing_section'])) {
            $content['pricing_section']['rate_percentage'] = $pricing['rate_percentage'];
            $content['pricing_section']['rate_fixed'] = $pricing['rate_fixed'];
            $content['pricing_section']['rate_description'] = $pricing['rate_description'];
            $content['pricing_section']['examples'] = $pricing['examples'];
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function mergeIntoPricingContent(array $content, ?array $pricing = null): array
    {
        $pricing ??= self::snapshot();

        if (isset($content['hero']) && is_array($content['hero'])) {
            $content['hero']['rate_percentage'] = $pricing['rate_percentage'];
            $content['hero']['rate_fixed'] = $pricing['rate_fixed'];
            $content['hero']['rate_description'] = $pricing['rate_description'];
        }

        if (isset($content['pricing_card']) && is_array($content['pricing_card'])) {
            $content['pricing_card']['rate_percentage'] = $pricing['rate_percentage'];
            $content['pricing_card']['rate_fixed'] = $pricing['rate_fixed'];
            $content['pricing_card']['rate_description'] = 'per transaction';
            $content['pricing_card']['examples'] = self::pricingPageExamples(
                $pricing['percentage'],
                $pricing['fixed']
            );
        }

        return $content;
    }

    /**
     * @return list<array{amount: string, calculation: string, fee: string}>
     */
    public static function pricingPageExamples(float $percentage, float $fixed): array
    {
        $amounts = [1000, 5000, 10000, 50000, 100000];

        return array_map(function (float $amount) use ($percentage, $fixed): array {
            $percentagePart = round($amount * $percentage / 100, 0);
            $fee = self::transactionFee($amount, $percentage, $fixed);

            return [
                'amount' => self::formatNaira($amount),
                'calculation' => self::formatPercentage($percentage).' = '.self::formatNaira($percentagePart).' + '.self::formatNaira($fixed),
                'fee' => self::formatNaira($fee),
            ];
        }, $amounts);
    }

    /**
     * @return list<array{amount: string, fee: string}>
     */
    public static function examples(float $percentage, float $fixed): array
    {
        $amounts = [1000, 5000, 10000, 50000, 100000];

        return array_map(function (float $amount) use ($percentage, $fixed): array {
            $fee = self::transactionFee($amount, $percentage, $fixed);

            return [
                'amount' => self::formatNaira($amount),
                'fee' => self::formatNaira($fee),
            ];
        }, $amounts);
    }

    public static function transactionFee(float $amount, float $percentage, float $fixed): float
    {
        return round(($amount * $percentage / 100) + $fixed, 2);
    }

    public static function formatNaira(float $amount): string
    {
        return '₦'.number_format($amount, 0);
    }

    public static function formatPercentage(float $percentage): string
    {
        $formatted = rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.');

        return $formatted.'%';
    }
}
