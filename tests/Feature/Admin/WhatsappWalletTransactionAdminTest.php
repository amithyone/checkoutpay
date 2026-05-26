<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use Illuminate\Support\Facades\Http;
use App\Services\MavonPayTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WhatsappWalletTransactionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    private function superAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Super',
            'email' => 'super-'.uniqid().'@check-outpay.com',
            'password' => Hash::make('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    private function regularAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@check-outpay.com',
            'password' => Hash::make('password'),
            'role' => Admin::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function walletWithTransaction(array $meta, string $type = WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT): WhatsappWalletTransaction
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+23480'.substr((string) uniqid(), -8),
            'balance' => 5000,
            'daily_transfer_total' => 1000,
            'daily_transfer_for_date' => now()->toDateString(),
        ]);

        return WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => 1000,
            'balance_after' => 4000,
            'external_reference' => 'WA-TEST-'.uniqid(),
            'counterparty_account_number' => '0123456789',
            'counterparty_account_name' => 'Test User',
            'meta' => $meta,
        ]);
    }

    public function test_guest_cannot_access_transactions_index(): void
    {
        $this->get(route('admin.whatsapp-wallet.transactions.index'))
            ->assertRedirect();
    }

    public function test_admin_can_list_failed_transactions(): void
    {
        $admin = $this->regularAdmin();
        $this->walletWithTransaction([
            'payout_bucket' => MavonPayTransferService::BUCKET_FAILED,
            'payout_failed' => true,
        ]);
        $this->walletWithTransaction([
            'payout_bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.whatsapp-wallet.transactions.failed'))
            ->assertOk()
            ->assertSee('Failed wallet payouts');
    }

    public function test_check_status_updates_meta_from_tsk_response(): void
    {
        config([
            'services.mevonpay.base_url' => 'https://mevonpay.com.ng',
            'services.mevonpay.secret_key' => 'secret_test',
            'services.mevonpay.transfer_status_path' => '/V1/tsk',
        ]);

        Http::fake([
            'mevonpay.com.ng/V1/tsk' => Http::response([
                'status' => 'success',
                'message' => 'Transaction status verification complete.',
                'reference' => 'waw_testref',
                'details' => [
                    'transactionStatus' => 'Success',
                    'responseCode' => '00',
                    'responseMessage' => 'Success',
                    'sessionId' => 'waw_testref',
                ],
            ], 200),
        ]);

        $admin = $this->regularAdmin();
        $txn = $this->walletWithTransaction([
            'external_reference' => 'waw_testref',
            'payout_bucket' => MavonPayTransferService::BUCKET_PENDING,
            'payout_pending' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.whatsapp-wallet.transactions.check-status', $txn))
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('bucket', MavonPayTransferService::BUCKET_SUCCESSFUL);

        $txn->refresh();
        $meta = is_array($txn->meta) ? $txn->meta : [];
        $this->assertSame(MavonPayTransferService::BUCKET_SUCCESSFUL, $meta['payout_bucket'] ?? null);
        $this->assertSame('00', $meta['mevonpay']['api_response']['responseCode'] ?? null);
    }

    public function test_check_status_returns_unavailable_when_path_not_configured(): void
    {
        config(['services.mevonpay.transfer_status_path' => '']);

        $admin = $this->regularAdmin();
        $txn = $this->walletWithTransaction([
            'payout_bucket' => MavonPayTransferService::BUCKET_PENDING,
            'payout_pending' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.whatsapp-wallet.transactions.check-status', $txn))
            ->assertOk()
            ->assertJsonPath('available', false);
    }

    public function test_super_admin_can_manual_refund_pending_payout(): void
    {
        $admin = $this->superAdmin();
        $txn = $this->walletWithTransaction([
            'payout_bucket' => MavonPayTransferService::BUCKET_PENDING,
            'payout_pending' => true,
        ]);
        $walletId = $txn->whatsapp_wallet_id;
        $balanceBefore = (float) WhatsappWallet::query()->find($walletId)->balance;

        $this->actingAs($admin, 'admin')
            ->post(route('admin.whatsapp-wallet.transactions.manual-refund', $txn))
            ->assertRedirect(route('admin.whatsapp-wallet.transactions.show', $txn));

        $txn->refresh();
        $wallet = WhatsappWallet::query()->find($walletId);

        $this->assertTrue($txn->isReversed());
        $this->assertEquals($balanceBefore + 1000, (float) $wallet->balance);
    }

    public function test_non_super_admin_cannot_manual_refund(): void
    {
        $admin = $this->regularAdmin();
        $txn = $this->walletWithTransaction([
            'payout_bucket' => MavonPayTransferService::BUCKET_PENDING,
            'payout_pending' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.whatsapp-wallet.transactions.manual-refund', $txn))
            ->assertForbidden();
    }
}
