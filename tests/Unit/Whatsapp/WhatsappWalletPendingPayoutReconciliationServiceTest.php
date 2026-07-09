<?php

namespace Tests\Unit\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;
use App\Services\Consumer\ConsumerWalletTransactionScope;
use App\Services\MavonPayTransferService;
use App\Services\Whatsapp\WhatsappWalletBankPayoutRefundService;
use App\Services\Whatsapp\WhatsappWalletPendingPayoutReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
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
            'whatsapp.wallet.payout_failed_confirmations_required' => 2,
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

    public function test_reconcile_wallet_does_not_refund_on_first_failed_status(): void
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
        $txn = $this->pendingTxn($wallet);

        $service = app(WhatsappWalletPendingPayoutReconciliationService::class);
        $out = $service->reconcileWallet($wallet);

        $this->assertSame(1, $out['checked']);
        $this->assertSame([], $out['refunds']);
        $wallet->refresh();
        $this->assertSame(4000.0, (float) $wallet->balance);
        $txn->refresh();
        $meta = is_array($txn->meta) ? $txn->meta : [];
        $this->assertSame(1, (int) ($meta['provider_failed_confirmations'] ?? 0));
        $this->assertSame(MavonPayTransferService::BUCKET_PENDING, $meta['payout_bucket'] ?? null);
        $this->assertTrue($meta['payout_pending'] ?? false);
        $this->assertFalse($txn->isReversed());
    }

    public function test_reconcile_wallet_refunds_after_second_failed_status(): void
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
        $txn = $this->pendingTxn($wallet);
        $txn->update([
            'meta' => array_merge(is_array($txn->meta) ? $txn->meta : [], [
                'provider_failed_confirmations' => 1,
            ]),
        ]);

        $service = app(WhatsappWalletPendingPayoutReconciliationService::class);
        $out = $service->reconcileWallet($wallet);

        $this->assertSame(1, $out['checked']);
        $this->assertCount(1, $out['refunds']);
        $wallet->refresh();
        $this->assertSame(5000.0, (float) $wallet->balance);
        $this->assertTrue($txn->fresh()->isReversed());

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
        $txn = $this->pendingTxn($wallet);
        $txn->update([
            'meta' => array_merge(is_array($txn->meta) ? $txn->meta : [], [
                'provider_failed_confirmations' => 1,
            ]),
        ]);

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

    public function test_refund_service_credits_business_ledger_for_business_scope(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345703',
            'balance' => 4000,
            'business_balance' => 10000,
        ]);
        $txn = WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
            'ledger_scope' => ConsumerWalletTransactionScope::SCOPE_BUSINESS,
            'amount' => 1000,
            'balance_after' => 9000,
            'external_reference' => 'waw_biz_refund',
            'meta' => [
                'payout_bucket' => MavonPayTransferService::BUCKET_PENDING,
                'payout_pending' => true,
            ],
        ]);

        $businessLedger = Mockery::mock(ConsumerBusinessWalletLedgerService::class);
        $businessLedger->shouldReceive('creditLockedWallet')
            ->once()
            ->andReturnUsing(function (WhatsappWallet $w, float $amount) {
                $w->business_balance = round((float) $w->business_balance + $amount, 2);
                $w->save();

                return ['ok' => true, 'balance_after' => (float) $w->business_balance];
            });
        $this->app->instance(ConsumerBusinessWalletLedgerService::class, $businessLedger);

        $refunds = app(WhatsappWalletBankPayoutRefundService::class);
        $result = $refunds->refundTransaction($txn, null, 'provider_status_failed');

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('business', strtolower($result['message']));
        $wallet->refresh();
        $this->assertSame(4000.0, (float) $wallet->balance);
        $this->assertSame(11000.0, (float) $wallet->business_balance);
        $txn->refresh();
        $meta = is_array($txn->meta) ? $txn->meta : [];
        $this->assertSame(ConsumerWalletTransactionScope::SCOPE_BUSINESS, $meta['refund_ledger_scope'] ?? null);
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

    public function test_reconcile_does_not_refund_when_provider_returns_code_25_unable_to_locate(): void
    {
        Http::fake([
            'mevonpay.com.ng/V1/tsk' => Http::response([
                'status' => 'success',
                'reference' => 'waw_pending1',
                'details' => [
                    'transactionStatus' => 'Unable to locate record',
                    'responseCode' => '25',
                    'responseMessage' => 'Unable to locate record',
                ],
            ], 200),
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345701',
            'balance' => 4000,
        ]);
        $txn = $this->pendingTxn($wallet);
        $txn->update([
            'meta' => array_merge(is_array($txn->meta) ? $txn->meta : [], [
                'mevonpay' => ['payout_api' => 'createtransfer'],
            ]),
        ]);

        $service = app(WhatsappWalletPendingPayoutReconciliationService::class);
        $out = $service->reconcileWallet($wallet);

        $this->assertSame(1, $out['checked']);
        $this->assertSame([], $out['refunds']);
        $wallet->refresh();
        $this->assertSame(4000.0, (float) $wallet->balance);
        $txn->refresh();
        $meta = is_array($txn->meta) ? $txn->meta : [];
        $this->assertSame(MavonPayTransferService::BUCKET_PENDING, $meta['payout_bucket'] ?? null);
        $this->assertTrue($meta['payout_pending'] ?? false);
        $this->assertFalse($txn->isReversed());

        Http::assertSent(function ($request) {
            return $request['reference'] === 'waw_pending1'
                && $request['payoutApi'] === 'createtransfer';
        });
    }

    public function test_admin_check_updates_meta_when_already_reversed_and_provider_now_successful(): void
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
            'phone_e164' => '+2348012345702',
            'balance' => 5000,
        ]);
        $txn = $this->pendingTxn($wallet);
        $txn->update([
            'meta' => [
                'payout_bucket' => MavonPayTransferService::BUCKET_FAILED,
                'payout_pending' => false,
                'payout_failed' => true,
                'reversed_at' => now()->toIso8601String(),
                'admin_refund_reason' => 'provider_status_failed',
                'payout_api' => 'createtransfer',
                'provider_status_bucket' => MavonPayTransferService::BUCKET_FAILED,
                'provider_status_response_code' => '25',
            ],
        ]);

        $service = app(WhatsappWalletPendingPayoutReconciliationService::class);
        $result = $service->reconcileTransaction($txn->fresh(), 1, onlyIfPending: false);

        $this->assertTrue($result['checked'] ?? false);
        $this->assertTrue($result['reversal_conflict'] ?? false);
        $this->assertArrayNotHasKey('auto_refund', $result);
        $wallet->refresh();
        $this->assertSame(5000.0, (float) $wallet->balance);

        $txn->refresh();
        $meta = is_array($txn->meta) ? $txn->meta : [];
        $this->assertSame(MavonPayTransferService::BUCKET_SUCCESSFUL, $meta['payout_bucket'] ?? null);
        $this->assertSame('00', $meta['provider_status_response_code'] ?? null);
        $this->assertTrue($meta['provider_success_after_reversal'] ?? false);
        $this->assertTrue($txn->isReversed());
        $this->assertSame(MavonPayTransferService::BUCKET_SUCCESSFUL, $txn->payoutBucketLabel());
    }

    public function test_reconcile_transaction_does_not_double_refund_when_already_reversed(): void
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

        $this->assertTrue($result['checked'] ?? false);
        $this->assertArrayNotHasKey('auto_refund', $result);
        Http::assertSentCount(1);
        $wallet->refresh();
        $this->assertSame(4000.0, (float) $wallet->balance);
    }

    public function test_lazy_reconcile_still_skips_already_reversed(): void
    {
        Http::fake();

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '+2348012345704',
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
        $result = $service->reconcileTransaction($txn->fresh(), null, onlyIfPending: true);

        $this->assertTrue($result['skipped'] ?? false);
        Http::assertNothingSent();
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
