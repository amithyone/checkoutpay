<?php

namespace Tests\Unit\Rentals;

use App\Models\Rental;
use App\Services\Rentals\RentalsPushContract;
use PHPUnit\Framework\TestCase;

class RentalsPushContractTest extends TestCase
{
    public function test_business_new_request_payload_includes_deep_link_fields(): void
    {
        $rental = new Rental;
        $rental->id = 41;
        $rental->rental_number = 'R-1042';

        $data = RentalsPushContract::rentalPayload(
            RentalsPushContract::TYPE_RENTAL_REQUEST_NEW,
            RentalsPushContract::ROLE_BUSINESS,
            $rental
        );

        $this->assertSame('rental_request_new', $data['type']);
        $this->assertSame('business', $data['role']);
        $this->assertSame('41', $data['rentalId']);
        $this->assertSame('41', $data['rental_id']);
        $this->assertSame('R-1042', $data['rentalNumber']);
        $this->assertSame('/manage/orders/41', $data['href']);
    }

    public function test_renter_approved_href(): void
    {
        $rental = new Rental;
        $rental->id = 7;
        $rental->rental_number = 'RENT-1';

        $data = RentalsPushContract::rentalPayload(
            RentalsPushContract::TYPE_RENTAL_APPROVED,
            RentalsPushContract::ROLE_RENTER,
            $rental
        );

        $this->assertSame('/order/7', $data['href']);
    }

    public function test_wallet_payload(): void
    {
        $data = RentalsPushContract::walletPayload(
            RentalsPushContract::TYPE_WALLET_CREDIT,
            RentalsPushContract::ROLE_RENTER,
            1500.5
        );

        $this->assertSame('wallet_credit', $data['type']);
        $this->assertSame('/wallet', $data['href']);
        $this->assertSame('1500.5', $data['amount']);
    }
}
