<?php

namespace Tests\Unit;

use App\Services\MevonPay\MevonPayPayoutPreRefundStatusService;
use App\Services\MavonPayTransferService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MevonPayPayoutPreRefundStatusServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.mevonpay.base_url' => 'https://mevonpay.test',
            'services.mevonpay.secret_key' => 'secret',
            'services.mevonpay.transfer_status_path' => '/V1/tsk',
        ]);
    }

    public function test_successful_initial_payout_skips_tsq(): void
    {
        Http::fake();

        $svc = app(MevonPayPayoutPreRefundStatusService::class);
        $out = $svc->resolveBeforeRefund([
            'bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
            'reference' => 'waw_ok',
        ], 'waw_ok');

        $this->assertSame(MavonPayTransferService::BUCKET_SUCCESSFUL, $out['bucket']);
        $this->assertFalse($out['refund_allowed']);
        $this->assertFalse($out['status_checked']);
        Http::assertNothingSent();
    }

    public function test_initial_failed_confirmed_by_tsq_held_pending_without_immediate_refund(): void
    {
        Http::fake([
            'mevonpay.test/V1/tsk' => Http::response([
                'status' => 'success',
                'reference' => 'waw_fail1',
                'details' => [
                    'transactionStatus' => 'Failed',
                    'responseCode' => '91',
                ],
            ], 200),
        ]);

        $svc = app(MevonPayPayoutPreRefundStatusService::class);
        $out = $svc->resolveBeforeRefund([
            'bucket' => MavonPayTransferService::BUCKET_FAILED,
            'response_message' => 'Declined',
            'reference' => 'waw_fail1',
        ], 'waw_fail1');

        $this->assertSame(MavonPayTransferService::BUCKET_PENDING, $out['bucket']);
        $this->assertFalse($out['refund_allowed']);
        $this->assertTrue($out['status_checked']);
        $this->assertSame(1, $out['result']['provider_failed_confirmations'] ?? null);
        Http::assertSentCount(1);
    }

    public function test_initial_failed_but_tsq_success_blocks_refund(): void
    {
        Http::fake([
            'mevonpay.test/V1/tsk' => Http::response([
                'status' => 'success',
                'reference' => 'waw_timeout1',
                'details' => [
                    'transactionStatus' => 'Successful',
                    'responseCode' => '00',
                ],
            ], 200),
        ]);

        $svc = app(MevonPayPayoutPreRefundStatusService::class);
        $out = $svc->resolveBeforeRefund([
            'bucket' => MavonPayTransferService::BUCKET_FAILED,
            'response_message' => 'time limit exceeded',
            'reference' => 'waw_timeout1',
        ], 'waw_timeout1');

        $this->assertSame(MavonPayTransferService::BUCKET_SUCCESSFUL, $out['bucket']);
        $this->assertFalse($out['refund_allowed']);
        $this->assertTrue($out['status_checked']);
    }

    public function test_initial_failed_but_tsq_code_25_stays_pending_without_refund(): void
    {
        Http::fake([
            'mevonpay.test/V1/tsk' => Http::response([
                'status' => 'success',
                'reference' => 'waw_not_found',
                'details' => [
                    'transactionStatus' => 'Unable to locate record',
                    'responseCode' => '25',
                    'responseMessage' => 'Unable to locate record',
                ],
            ], 200),
        ]);

        $svc = app(MevonPayPayoutPreRefundStatusService::class);
        $out = $svc->resolveBeforeRefund([
            'bucket' => MavonPayTransferService::BUCKET_FAILED,
            'response_message' => 'time limit exceeded',
            'reference' => 'waw_not_found',
            'payout_api' => 'createtransfer',
        ], 'waw_not_found');

        $this->assertSame(MavonPayTransferService::BUCKET_PENDING, $out['bucket']);
        $this->assertFalse($out['refund_allowed']);
        $this->assertTrue($out['status_checked']);
        Http::assertSent(function ($request) {
            return $request['reference'] === 'waw_not_found'
                && $request['payoutApi'] === 'createtransfer';
        });
    }

    public function test_initial_failed_with_tsq_timeout_stays_pending_without_refund(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
        });

        $svc = app(MevonPayPayoutPreRefundStatusService::class);
        $out = $svc->resolveBeforeRefund([
            'bucket' => MavonPayTransferService::BUCKET_FAILED,
            'response_message' => 'Declined',
            'reference' => 'waw_tsq_down',
        ], 'waw_tsq_down');

        $this->assertSame(MavonPayTransferService::BUCKET_PENDING, $out['bucket']);
        $this->assertFalse($out['refund_allowed']);
        $this->assertFalse($out['status_checked']);
    }
}
