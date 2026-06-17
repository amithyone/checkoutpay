<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\ConsumerWalletApiAccount;
use App\Models\Payment;
use App\Models\WhatsappWallet;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerBusinessActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_scope_includes_website_payments_and_withdrawals_in_date_range(): void
    {
        $business = Business::create([
            'name' => 'Acme Store',
            'email' => 'acme@example.com',
            'password' => Hash::make('secret'),
            'business_id' => 'ACME1',
            'phone' => '08012345678',
            'balance' => 50000,
        ]);

        $website = BusinessWebsite::create([
            'business_id' => $business->id,
            'website_url' => 'https://shop.example.com',
            'is_approved' => true,
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348012345678',
            'balance' => 1000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'linked_business_id' => $business->id,
            'business_balance' => 50000,
        ]);

        ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        Payment::query()->create([
            'transaction_id' => 'TX-WEB-001',
            'amount' => 10000,
            'business_receives' => 9500,
            'business_id' => $business->id,
            'business_website_id' => $website->id,
            'status' => Payment::STATUS_APPROVED,
            'payment_source' => Payment::SOURCE_INTERNAL,
            'matched_at' => now(),
        ]);

        WithdrawalRequest::query()->create([
            'business_id' => $business->id,
            'amount' => 2000,
            'account_number' => '0123456789',
            'account_name' => 'Acme Store',
            'bank_name' => 'GTBank',
            'status' => WithdrawalRequest::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);

        Sanctum::actingAs(
            ConsumerWalletApiAccount::query()->where('whatsapp_wallet_id', $wallet->id)->first(),
            ['consumer']
        );

        $from = now()->subDays(7)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $response = $this->getJson('/api/v1/consumer/wallet/transactions?scope=business&from='.$from.'&to='.$to.'&per_page=50');

        $response->assertOk()
            ->assertJsonPath('meta.includes_merchant_activity', true)
            ->assertJsonPath('meta.business_id', $business->id);

        $types = collect($response->json('data'))->pluck('type')->all();
        $this->assertContains('merchant_payment_in', $types);
        $this->assertContains('merchant_withdrawal_out', $types);
    }
}
