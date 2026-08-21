<?php

namespace Tests\Unit\MevonPay;

use App\Services\MevonPay\MevonPayFeeCalculator;
use Tests\TestCase;

class MevonPayFeeCalculatorTest extends TestCase
{
    private MevonPayFeeCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mevonpay_fees.inbound_threshold' => 10000,
            'mevonpay_fees.inbound_fee_below' => 30,
            'mevonpay_fees.inbound_fee_at_or_above' => 50,
            'mevonpay_fees.outbound_api_fee' => 10,
        ]);
        $this->calc = new MevonPayFeeCalculator;
    }

    public function test_inbound_fee_below_threshold(): void
    {
        $this->assertSame(30, $this->calc->inboundFee(5000));
        $this->assertSame(30, $this->calc->inboundFee(9999.99));
    }

    public function test_inbound_fee_at_threshold(): void
    {
        $this->assertSame(50, $this->calc->inboundFee(10000));
    }

    public function test_net_outbound_impact(): void
    {
        $this->assertSame(-1010.0, $this->calc->netOutboundImpact(1000));
        $this->assertSame(0.0, $this->calc->netOutboundImpact(1000, false));
    }

    public function test_inbound_net_impact_is_gross_minus_fee(): void
    {
        $this->assertSame(1970.0, $this->calc->netInboundImpact(2000));
        $this->assertSame(9950.0, $this->calc->netInboundImpact(10000));
        $breakdown = $this->calc->inboundBreakdown(2000);
        $this->assertSame(30, $breakdown['inbound_fee']);
        $this->assertSame(1970.0, $breakdown['net_mevon_impact']);
    }

    public function test_failed_outbound_breakdown_is_zero(): void
    {
        $breakdown = $this->calc->outboundBreakdown(500, false);
        $this->assertSame(0, $breakdown['outbound_fee']);
        $this->assertSame(0.0, $breakdown['net_mevon_impact']);
    }
}
