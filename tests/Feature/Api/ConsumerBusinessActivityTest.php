<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\BusinessWebsite;
use App\Models\ConsumerWalletApiAccount;
use App\Models\Payment;
use App\Models\WhatsappWallet;
use App\Models\WithdrawalRequest;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerBusinessActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_scope_includes_website_payments_and_withdrawals_in_date_range(): void
    {
        $this->withoutExceptionHandling();
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
            'webhook_url' => 'https://example.com/webhook',
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

    public function test_business_scope_without_dates_includes_merchant_withdrawals(): void
    {
        $business = Business::create([
            'name' => 'Acme Store',
            'email' => 'acme2@example.com',
            'password' => Hash::make('secret'),
            'business_id' => 'ACME2',
            'phone' => '08087654321',
            'balance' => 50000,
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348087654321',
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

        WithdrawalRequest::query()->create([
            'business_id' => $business->id,
            'amount' => 1500,
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

        $response = $this->getJson('/api/v1/consumer/wallet/transactions?scope=business&business_view=account&per_page=50');

        $response->assertOk()
            ->assertJsonPath('meta.includes_merchant_activity', true)
            ->assertJsonPath('meta.business_id', $business->id);

        $types = collect($response->json('data'))->pluck('type')->all();
        $this->assertContains('merchant_withdrawal_out', $types);
        $this->assertNotEmpty($response->json('meta.from'));
        $this->assertNotEmpty($response->json('meta.to'));
        $this->assertSame('account', $response->json('meta.business_view'));
    }

    public function test_business_account_view_includes_app_bank_transfers_and_merchant_rows(): void
    {
        $business = Business::create([
            'name' => 'Acme Store',
            'email' => 'acme3@example.com',
            'password' => Hash::make('secret'),
            'business_id' => 'ACME3',
            'phone' => '08011112222',
            'balance' => 50000,
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348011112222',
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

        \App\Models\WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => \App\Models\WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
            'ledger_scope' => 'business',
            'amount' => 3000,
            'balance_after' => 47000,
            'counterparty_account_name' => 'Someone',
            'created_at' => now(),
        ]);

        Payment::query()->create([
            'transaction_id' => 'TX-ACC-001',
            'amount' => 5000,
            'business_receives' => 5000,
            'business_id' => $business->id,
            'webhook_url' => 'https://example.com/webhook',
            'status' => Payment::STATUS_APPROVED,
            'payment_source' => Payment::SOURCE_INTERNAL,
            'matched_at' => now(),
        ]);

        WithdrawalRequest::query()->create([
            'business_id' => $business->id,
            'amount' => 1000,
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

        $account = $this->getJson('/api/v1/consumer/wallet/transactions?scope=business&business_view=account&from='.$from.'&to='.$to.'&per_page=50');
        $accountTypes = collect($account->json('data'))->pluck('type')->all();
        $this->assertContains('merchant_payment_in', $accountTypes);
        $this->assertContains('merchant_withdrawal_out', $accountTypes);
        $this->assertContains('bank_transfer_out', $accountTypes);

        $full = $this->getJson('/api/v1/consumer/wallet/transactions?scope=business&business_view=full&from='.$from.'&to='.$to.'&per_page=50');
        $fullTypes = collect($full->json('data'))->pluck('type')->all();
        $this->assertContains('bank_transfer_out', $fullTypes);
    }

    public function test_merchant_activity_before_link_appears_after_sync_balance_from_linked_business(): void
    {
        $business = Business::create([
            'name' => 'Late Link Store',
            'email' => 'latelink@example.com',
            'password' => Hash::make('secret'),
            'business_id' => 'LATE1',
            'phone' => '08033334444',
            'balance' => 42000,
        ]);

        $website = BusinessWebsite::create([
            'business_id' => $business->id,
            'website_url' => 'https://late.example.com',
            'is_approved' => true,
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348033334444',
            'balance' => 1000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'linked_business_id' => null,
            'business_balance' => 0,
        ]);

        ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        Payment::query()->create([
            'transaction_id' => 'TX-LATE-001',
            'amount' => 8000,
            'business_receives' => 7600,
            'business_id' => $business->id,
            'business_website_id' => $website->id,
            'webhook_url' => 'https://example.com/webhook',
            'status' => Payment::STATUS_APPROVED,
            'payment_source' => Payment::SOURCE_INTERNAL,
            'matched_at' => now()->subDay(),
        ]);

        WithdrawalRequest::query()->create([
            'business_id' => $business->id,
            'amount' => 2500,
            'account_number' => '0123456789',
            'account_name' => 'Late Link Store',
            'bank_name' => 'GTBank',
            'status' => WithdrawalRequest::STATUS_PROCESSED,
            'processed_at' => now()->subDay(),
        ]);

        \App\Models\WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => \App\Models\WhatsappWalletTransaction::TYPE_BUSINESS_RUBIES_IN,
            'ledger_scope' => 'business',
            'amount' => 5000,
            'balance_after' => 5000,
            'created_at' => now()->subDay(),
        ]);

        \App\Models\WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => \App\Models\WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
            'ledger_scope' => 'business',
            'amount' => 1200,
            'balance_after' => 3800,
            'counterparty_account_name' => 'Vendor',
            'created_at' => now()->subDay(),
        ]);

        app(ConsumerBusinessWalletLedgerService::class)->syncBalanceFromLinkedBusiness($wallet, $business);

        Sanctum::actingAs(
            ConsumerWalletApiAccount::query()->where('whatsapp_wallet_id', $wallet->id)->first(),
            ['consumer']
        );

        $from = now()->subDays(7)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $response = $this->getJson('/api/v1/consumer/wallet/transactions?scope=business&business_view=account&from='.$from.'&to='.$to.'&per_page=50');

        $response->assertOk()
            ->assertJsonPath('meta.includes_merchant_activity', true)
            ->assertJsonPath('meta.business_id', $business->id)
            ->assertJsonPath('meta.business_view', 'account');

        $types = collect($response->json('data'))->pluck('type')->all();
        $this->assertContains('merchant_payment_in', $types);
        $this->assertContains('merchant_withdrawal_out', $types);
        $this->assertContains('business_rubies_in', $types);
        $this->assertContains('bank_transfer_out', $types);
    }

    public function test_broken_linked_business_returns_empty_with_merchant_link_broken_meta(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348099998888',
            'balance' => 1000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'linked_business_id' => 999999, // Non-existent business ID
            'business_balance' => 0,
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        Sanctum::actingAs($account, ['consumer']);

        $response = $this->getJson('/api/v1/consumer/wallet/transactions?scope=business&business_view=account&per_page=20');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.includes_merchant_activity', false)
            ->assertJsonPath('meta.merchant_link_broken', true)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.business_view', 'account');
    }

    public function test_unlinked_business_wallet_fallback_filters_by_account_view(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348077776666',
            'balance' => 1000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'linked_business_id' => null,
            'business_balance' => 5000,
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        // Account view allowed: business_rubies_in, bank_transfer_out
        \App\Models\WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => \App\Models\WhatsappWalletTransaction::TYPE_BUSINESS_RUBIES_IN,
            'ledger_scope' => 'business',
            'amount' => 5000,
            'balance_after' => 5000,
            'created_at' => now()->subDay(),
        ]);

        \App\Models\WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => \App\Models\WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
            'ledger_scope' => 'business',
            'amount' => 1500,
            'balance_after' => 3500,
            'counterparty_account_name' => 'Supplier',
            'created_at' => now()->subDay(),
        ]);

        // Utility only: airtime / VTU row
        \App\Models\WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => \App\Models\WhatsappWalletTransaction::TYPE_VTU_AIRTIME,
            'ledger_scope' => 'business',
            'amount' => 500,
            'balance_after' => 3000,
            'created_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($account, ['consumer']);

        // Account view
        $accountResp = $this->getJson('/api/v1/consumer/wallet/transactions?scope=business&business_view=account&per_page=20');
        $accountResp->assertOk()
            ->assertJsonPath('meta.includes_merchant_activity', false)
            ->assertJsonPath('meta.business_view', 'account')
            ->assertJsonPath('meta.total', 2);

        $accountTypes = collect($accountResp->json('data'))->pluck('type')->all();
        $this->assertContains('business_rubies_in', $accountTypes);
        $this->assertContains('bank_transfer_out', $accountTypes);
        $this->assertNotContains('vtu_airtime', $accountTypes);

        // Full view
        $fullResp = $this->getJson('/api/v1/consumer/wallet/transactions?scope=business&business_view=full&per_page=20');
        $fullResp->assertOk()
            ->assertJsonPath('meta.includes_merchant_activity', false)
            ->assertJsonPath('meta.business_view', 'full')
            ->assertJsonPath('meta.total', 3);

        $fullTypes = collect($fullResp->json('data'))->pluck('type')->all();
        $this->assertContains('vtu_airtime', $fullTypes);
    }

    public function test_forget_wallet_caches_invalidates_cached_transactions_on_unlink_and_link(): void
    {
        $business = Business::create([
            'name' => 'Cache Test Store',
            'email' => 'cache@example.com',
            'password' => Hash::make('secret'),
            'business_id' => 'CACHE1',
            'phone' => '08055556666',
            'balance' => 10000,
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348055556666',
            'balance' => 1000,
            'pin_hash' => Hash::make('2468'),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'linked_business_id' => $business->id,
            'business_balance' => 10000,
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
        ]);

        Payment::query()->create([
            'transaction_id' => 'TX-CACHE-001',
            'amount' => 5000,
            'business_receives' => 5000,
            'business_id' => $business->id,
            'webhook_url' => 'https://example.com/webhook',
            'status' => Payment::STATUS_APPROVED,
            'payment_source' => Payment::SOURCE_INTERNAL,
            'matched_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($account, ['consumer']);

        // First fetch populates cache
        $firstResp = $this->getJson('/api/v1/consumer/wallet/transactions?scope=business&business_view=account&per_page=20');
        $firstResp->assertOk()->assertJsonPath('meta.total', 1);

        // Add a new payment while cached
        Payment::query()->create([
            'transaction_id' => 'TX-CACHE-002',
            'amount' => 3000,
            'business_receives' => 3000,
            'business_id' => $business->id,
            'webhook_url' => 'https://example.com/webhook',
            'status' => Payment::STATUS_APPROVED,
            'payment_source' => Payment::SOURCE_INTERNAL,
            'matched_at' => now()->subHours(2),
        ]);

        // Unlink via service (which calls forgetWalletCaches)
        $unlinkService = app(\App\Services\Business\BusinessWhatsappWalletLinkService::class);
        $unlinkResult = $unlinkService->unlink($business);
        $this->assertTrue($unlinkResult['ok']);

        // Re-link to test cache invalidation on link
        app(ConsumerBusinessWalletLedgerService::class)->syncBalanceFromLinkedBusiness($wallet, $business);

        // Fetching again should reflect the invalidated cache and show total 2
        $refetched = $this->getJson('/api/v1/consumer/wallet/transactions?scope=business&business_view=account&per_page=20');
        $refetched->assertOk()->assertJsonPath('meta.total', 2);
    }
}
