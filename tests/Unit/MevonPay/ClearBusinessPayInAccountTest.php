<?php

namespace Tests\Unit\MevonPay;

use App\Models\Business;
use App\Services\MevonPay\PrivateAccountProvisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearBusinessPayInAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_clear_removes_pay_in_fields(): void
    {
        $business = Business::create([
            'name' => 'Acme Ltd',
            'email' => 'acme-clear-'.uniqid().'@test.com',
            'api_key' => 'pk_clear_'.uniqid(),
            'is_active' => true,
            'balance' => 0,
            'cac_registration_number' => 'RC1234567',
            'rubies_business_account_number' => '1234567890',
            'rubies_business_account_name' => 'JOHN DOE',
            'rubies_business_bank_name' => 'Wema Bank',
            'rubies_business_bank_code' => '035',
            'rubies_account_provision_status' => 'completed',
        ]);

        $cleared = app(PrivateAccountProvisionService::class)->clearBusinessPayInAccount($business, 'test');

        $this->assertTrue($cleared);
        $business->refresh();
        $this->assertNull($business->rubies_business_account_number);
        $this->assertNull($business->rubies_business_account_name);
        $this->assertNull($business->rubies_account_provision_status);
        $this->assertSame('Acme Ltd', $business->name);
        $this->assertSame('RC1234567', $business->cac_registration_number);
    }
}
