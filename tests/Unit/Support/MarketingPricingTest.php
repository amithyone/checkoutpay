<?php

namespace Tests\Unit\Support;

use App\Support\MarketingPricing;
use Tests\TestCase;

class MarketingPricingTest extends TestCase
{
    public function test_examples_use_percentage_plus_fixed_fee(): void
    {
        $examples = MarketingPricing::examples(1.0, 100.0);

        $this->assertSame('₦110', $examples[0]['fee']);
        $this->assertSame('₦200', $examples[2]['fee']);
        $this->assertSame('₦1,100', $examples[4]['fee']);
    }

    public function test_merge_into_home_content_updates_pricing_fields(): void
    {
        $merged = MarketingPricing::mergeIntoHomeContent([
            'hero' => ['pricing_text' => 'old'],
            'pricing_section' => [
                'rate_percentage' => 'old',
                'rate_fixed' => 'old',
                'examples' => [],
            ],
        ], [
            'pricing_text' => '1% + ₦100 per transaction',
            'rate_percentage' => '1%',
            'rate_fixed' => '₦100',
            'rate_description' => 'per successful transaction',
            'examples' => [['amount' => '₦1,000', 'fee' => '₦110']],
        ]);

        $this->assertSame('1% + ₦100 per transaction', $merged['hero']['pricing_text']);
        $this->assertSame('₦100', $merged['pricing_section']['rate_fixed']);
        $this->assertSame('₦110', $merged['pricing_section']['examples'][0]['fee']);
    }

    public function test_merge_into_pricing_content_updates_rates_and_examples(): void
    {
        $merged = MarketingPricing::mergeIntoPricingContent([
            'hero' => ['rate_fixed' => 'old'],
            'pricing_card' => [
                'rate_fixed' => 'old',
                'examples' => [],
            ],
        ], [
            'percentage' => 1.0,
            'fixed' => 100.0,
            'rate_percentage' => '1%',
            'rate_fixed' => '₦100',
            'rate_description' => 'per successful transaction',
        ]);

        $this->assertSame('₦100', $merged['hero']['rate_fixed']);
        $this->assertSame('₦100', $merged['pricing_card']['rate_fixed']);
        $this->assertSame('₦110', $merged['pricing_card']['examples'][0]['fee']);
    }
}
