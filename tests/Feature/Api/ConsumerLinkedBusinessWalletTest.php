<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerLinkedBusinessWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_includes_linked_merchant_rubies_account_as_business_pay_in(): void
    {
        $business = Business::create([
            'name' => 'Switch Merchant Ltd',
            'email' => 'switch@example.com',
            'password' => Hash::make('secret'),
            'business_id' => '1RK8Z',
            'balance' => 250000,
            'rubies_business_account_number' => '9988776655',
            'rubies_business_account_name' => 'Switch Merchant Ltd',
            'rubies_business_bank_name' => 'Rubies MFB',
            'rubies_business_bank_code' => '090175',
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348012345678',
            'balance' => 50000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'sender_name' => 'Jane',
            'linked_business_id' => $business->id,
            'business_balance' => 250000,
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        Sanctum::actingAs($account, ['consumer']);

        $this->getJson('/api/v1/consumer/wallet')
            ->assertOk()
            ->assertJsonPath('data.business_pay_in.account_number', '9988776655')
            ->assertJsonPath('data.business_pay_in.account_name', 'Switch Merchant Ltd')
            ->assertJsonPath('data.business_pay_in.business_name', 'Switch Merchant Ltd')
            ->assertJsonPath('data.business_pay_in.bank_name', 'Rubies MFB')
            ->assertJsonPath('data.business_pay_in.source', 'linked_merchant')
            ->assertJsonPath('data.business_balance', 250000)
            ->assertJsonPath('data.linked_business_name', 'Switch Merchant Ltd');
    }

    public function test_wallet_includes_phone_matched_merchant_permanent_account(): void
    {
        Business::create([
            'name' => 'I Teach Globally Enterprises LTD',
            'email' => 'iteach@example.com',
            'password' => Hash::make('secret'),
            'business_id' => '1RK9Z',
            'phone' => '08088876785',
            'balance' => 10000,
            'rubies_business_account_number' => '1000004772',
            'rubies_business_account_name' => 'I Teach Globally Enterprises LTD',
            'rubies_business_bank_name' => 'Rubies MFB',
            'rubies_business_bank_code' => '090175',
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348088876785',
            'balance' => 5000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'sender_name' => 'Owner',
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        Sanctum::actingAs($account, ['consumer']);

        $this->getJson('/api/v1/consumer/wallet')
            ->assertOk()
            ->assertJsonPath('data.business_pay_in.account_number', '1000004772')
            ->assertJsonPath('data.business_pay_in.business_name', 'I Teach Globally Enterprises LTD')
            ->assertJsonPath('data.business_pay_in.source', 'phone_matched');
    }

    public function test_rubies_deposit_appears_in_business_transaction_history(): void
    {
        $business = Business::create([
            'name' => 'Switch Merchant Ltd',
            'email' => 'switch2@example.com',
            'password' => Hash::make('secret'),
            'business_id' => '1RK8Z',
            'balance' => 100000,
            'rubies_business_account_number' => '1122334455',
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348098765432',
            'balance' => 1000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'linked_business_id' => $business->id,
            'business_balance' => 100000,
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        $payment = \App\Models\Payment::query()->create([
            'transaction_id' => 'BRBTEST001',
            'amount' => 50000,
            'business_receives' => 49500,
            'payer_name' => 'Ada Lovelace',
            'bank' => 'GTBank',
            'webhook_url' => 'https://example.com/hook',
            'account_number' => '1122334455',
            'business_id' => $business->id,
            'status' => \App\Models\Payment::STATUS_APPROVED,
            'payment_source' => \App\Models\Payment::SOURCE_BUSINESS_RUBIES_VA,
            'external_reference' => 'REF123456',
        ]);

        app(\App\Services\Consumer\ConsumerBusinessWalletLedgerService::class)
            ->recordLinkedMerchantRubiesDeposit($business, $payment, 50000, [
                'sender' => 'Ada Lovelace',
                'bank_name' => 'GTBank',
            ]);

        Sanctum::actingAs($account, ['consumer']);

        $this->getJson('/api/v1/consumer/wallet/transactions?scope=business')
            ->assertOk()
            ->assertJsonPath('meta.scope', 'business')
            ->assertJsonPath('data.0.type', 'business_rubies_in')
            ->assertJsonPath('data.0.amount', 49500)
            ->assertJsonPath('data.0.counterparty_account_name', 'Ada Lovelace');
    }
}
