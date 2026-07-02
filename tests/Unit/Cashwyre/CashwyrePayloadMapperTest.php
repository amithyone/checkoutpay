<?php

namespace Tests\Unit\Cashwyre;

use App\Services\Cashwyre\CashwyrePayloadMapper;
use Tests\TestCase;

class CashwyrePayloadMapperTest extends TestCase
{
    public function test_create_card_payload_maps_cashwyre_fields(): void
    {
        config([
            'cashwyre.default_card_brand' => 'Visa',
            'cashwyre.default_phone_code' => '+234',
        ]);

        $mapped = app(CashwyrePayloadMapper::class)->createCardPayload([
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'ada@example.com',
            'phoneNumber' => '08034567890',
            'dob' => '1990-04-15',
            'homeNumber' => '12B',
            'homeAddress' => 'Lagos, Nigeria',
            'cardName' => 'Ada Lovelace',
            'amount' => 25.5,
        ]);

        $this->assertSame('Ada', $mapped['firstName']);
        $this->assertSame('Lovelace', $mapped['lastName']);
        $this->assertSame('+234', $mapped['phoneCode']);
        $this->assertSame('8034567890', $mapped['phoneNumber']);
        $this->assertSame('1990-04-15T00:00:00Z', $mapped['dateOfBirth']);
        $this->assertSame('virtual', $mapped['cardType']);
        $this->assertSame('Visa', $mapped['cardBrand']);
        $this->assertSame(25.5, $mapped['amountInUSD']);
    }
}
