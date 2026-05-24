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
    }
}
