<?php

namespace Tests\Unit;

use App\Services\ChargeService;
use Tests\TestCase;

class ChargeServiceTest extends TestCase
{
    public function test_checkout_payment_of_3000_deducts_default_fees(): void
    {
        $charges = app(ChargeService::class)->calculateCharges(3000.0, null, null);

        $this->assertFalse($charges['exempt']);
        $this->assertFalse($charges['paid_by_customer']);
        $this->assertEquals(30.0, $charges['charge_percentage']);
        $this->assertEquals(50.0, $charges['charge_fixed']);
        $this->assertEquals(80.0, $charges['total_charges']);
        $this->assertEquals(3000.0, $charges['amount_to_pay']);
        $this->assertEquals(2920.0, $charges['business_receives']);
    }

    public function test_does_not_round_settlement_to_nearest_500(): void
    {
        $charges = app(ChargeService::class)->calculateCharges(5000.0, null, null);

        $this->assertEquals(100.0, $charges['total_charges']);
        $this->assertEquals(4900.0, $charges['business_receives']);
    }
}
