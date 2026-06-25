<?php

namespace Tests\Unit\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\MavonPayTransferService;
use App\Services\Whatsapp\WhatsappWalletBankPayoutRefundService;
use App\Services\Whatsapp\WhatsappWalletPendingPayoutReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappWalletPendingPayoutReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'services.mevonpay.base_url' => 'https://mevonpay.com.ng',
            'services.mevonpay.secret_key' => 'secret_test',
            'services.mevonpay.transfer_status_path' => '/V1/tsk',
            'whatsapp.wallet.payout_reconcile_hours' => 48,
            'whatsapp.wallet.payout_reconcile_min_interval_minutes' => 5,
            'whatsapp.wallet.payout_reconcile_max_per_trigger' => 3,
        ]);
    }

    private function pendingTxn(WhatsappWallet $wallet, string $ref = 'waw_pending1'): WhatsappWalletTransaction
    {
        return WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
            'amount' => 1000,
            'balance_after' => 4000,
            'external_reference' => $ref,
            'meta' => [
                'payout_bucket' => MavonPayTransferService::BUCKET_PENDING,
                'payout_pending' => true,
            ],
        ]);
    }

    public function test_reconcile_wallet_refunds_when_provider_returns_failed(): void
    {
        Http::fake([
            'mevonpay.com.ng/V1/tsk' => Http::response([
                'status' => 'success',
                'reference' => 'waw_pending1',
                'details' => [
                    'transactionStatus' => 'Failed',
                    'responseCode' => '91',
                    'responseMessage' => 'Failed',
                ],
            ], 200),
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345678',
            'balance' => 4000,
        ]);
        $this->pendingTxn($wallet);

        $service = app(WhatsappWalletPendingPayoutReconciliationService::class);
        $out = $service->reconcileWallet($wallet);

        $this->assertSame(1, $out['checked']);
        $this->assertCount(1, $out['refunds']);
        $wallet->refresh();
        $this->assertSame(5000.0, (float) $wallet->balance);

        Http::assertSentCount(1);
    }

    public function test_reconcile_wallet_does_not_refund_when_provider_returns_successful(): void
    {
        Http::fake([
            'mevonpay.com.ng/V1/tsk' => Http::response([
                'status' => 'success',
                'reference' => 'waw_pending1',
                'details' => [
                    'transactionStatus' => 'Success',
                    'responseCode' => '00',
                    'responseMessage' => 'Success',
                ],
            ], 200),
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345679',
            'balance' => 4000,
        ]);
        $txn = $this->pendingTxn($wallet);

        $service = app(WhatsappWalletPendingPayoutReconciliationService::class);
        $out = $service->reconcileWallet($wallet);

        $this->assertSame(1, $out['checked']);
        $this->assertSame([], $out['refunds']);
        $wallet->refresh();
        $this->assertSame(4000.0, (float) $wallet->balance);
        $txn->refresh();
        $meta = is_array($txn->meta) ? $txn->meta : [];
        $this->assertFalse($meta['payout_pending'] ?? true);
        $this->assertSame(MavonPayTransferService::BUCKET_SUCCESSFUL, $meta['payout_bucket'] ?? null);
    }

    public function test_second_reconcile_does_not_double_credit(): void
    {
        Http::fake([
            'mevonpay.com.ng/V1/tsk' => Http::response([
                'status' => 'success',
                'reference' => 'waw_pending1',
                'details' => [
                    'transactionStatus' => 'Failed',
                    'responseCode' => '91',
                ],
            ], 200),
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345680',
            'balance' => 4000,
        ]);
        $this->pendingTxn($wallet);

        $service = app(WhatsappWalletPendingPayoutReconciliationService::class);
        $service->reconcileWallet($wallet);
        $wallet->refresh();
        $this->assertSame(5000.0, (float) $wallet->balance);

        $out = $service->reconcileWallet($wallet);
        $this->assertSame([], $out['refunds']);
        $wallet->refresh();
        $this->assertSame(5000.0, (float) $wallet->balance);
    }

    public function test_throttle_skips_second_check_within_interval(): void
    {
        Http::fake([
            'mevonpay.com.ng/V1/tsk' => Http::response([
                'status' => 'success',
                'reference' => 'waw_pending1',
                'details' => ['responseCode' => '09', 'transactionStatus' => 'Pending'],
            ], 200),
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345681',
            'balance' => 4000,
        ]);
        $this->pendingTxn($wallet);

        $service = app(WhatsappWalletPendingPayoutReconciliationService::class);
        $service->reconcileWallet($wallet);
        $service->reconcileWallet($wallet);

        Http::assertSentCount(1);
    }

    public function test_refund_service_only_credits_wallet_once(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345683',
            'balance' => 4000,
        ]);
        $txn = $this->pendingTxn($wallet);

        $refunds = app(WhatsappWalletBankPayoutRefundService::class);
        $first = $refunds->refundTransaction($txn, null, 'provider_status_failed');
        $second = $refunds->refundTransaction($txn->fresh(), null, 'provider_status_failed');

        $this->assertTrue($first['ok']);
        $this->assertFalse($second['ok']);
        $this->assertStringContainsString('already reversed', strtolower($second['message']));
        $wallet->refresh();
        $this->assertSame(5000.0, (float) $wallet->balance);
    }

    public function test_reconcile_transaction_skips_refund_when_tsq_times_out(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
        });

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345699',
            'balance' => 4000,
        ]);
        $txn = $this->pendingTxn($wallet, 'waw_tsq_timeout');

        $service = app(WhatsappWalletPendingPayoutReconciliationService::class);
        $result = $service->reconcileTransaction($txn, null, onlyIfPending: false);

        $this->assertFalse($result['available'] ?? true);
        $this->assertTrue($result['skipped'] ?? false);
        $wallet->refresh();
        $this->assertSame(4000.0, (float) $wallet->balance);
        $this->assertFalse($txn->fresh()->isReversed());
    }

    public function test_reconcile_transaction_skips_refund_when_already_reversed(): void
    {
        Http::fake([
            'mevonpay.com.ng/V1/tsk' => Http::response([
                'status' => 'success',
                'reference' => 'waw_pending1',
                'details' => [
                    'transactionStatus' => 'Failed',
                    'responseCode' => '91',
                ],
            ], 200),
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345684',
            'balance' => 4000,
        ]);
        $txn = $this->pendingTxn($wallet);
        $txn->update([
            'meta' => array_merge(is_array($txn->meta) ? $txn->meta : [], [
                'reversed_at' => now()->toIso8601String(),
                'payout_pending' => false,
                'payout_bucket' => MavonPayTransferService::BUCKET_FAILED,
            ]),
        ]);

        $service = app(WhatsappWalletPendingPayoutReconciliationService::class);
        $result = $service->reconcileTransaction($txn->fresh(), null, onlyIfPending: false);

        $this->assertTrue($result['skipped'] ?? false);
        Http::assertNothingSent();
        $wallet->refresh();
        $this->assertSame(4000.0, (float) $wallet->balance);
    }

    public function test_wallet_without_pending_skips_http(): void
    {
        Http::fake();

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345682',
            'balance' => 5000,
        ]);
        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
            'amount' => 1000,
            'balance_after' => 4000,
            'external_reference' => 'waw_ok',
            'meta' => [
                'payout_bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
                'payout_pending' => false,
            ],
        ]);

        $service = app(WhatsappWalletPendingPayoutReconciliationService::class);
        $out = $service->reconcileWallet($wallet);

        $this->assertSame(0, $out['checked']);
        Http::assertNothingSent();
    }
}
