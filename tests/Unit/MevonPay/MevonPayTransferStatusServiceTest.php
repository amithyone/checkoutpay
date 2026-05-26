<?php

namespace Tests\Unit\MevonPay;

use App\Services\MevonPay\MevonPayTransferStatusService;
use App\Services\MavonPayTransferService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MevonPayTransferStatusServiceTest extends TestCase
{
    public function test_check_status_parses_tsk_response_with_details(): void
    {
        config([
            'services.mevonpay.base_url' => 'https://mevonpay.com.ng',
            'services.mevonpay.secret_key' => 'secret_test_key',
            'services.mevonpay.transfer_status_path' => '/V1/tsk',
            'services.mevonpay.transfer_status_auth' => 'bearer',
        ]);

        Http::fake([
            'mevonpay.com.ng/V1/tsk' => Http::response([
                'status' => 'success',
                'message' => 'Transaction status verification complete.',
                'reference' => 'waw_wdfysny1kurtlx',
                'details' => [
                    'amount' => '3940.00',
                    'bankCode' => '100004',
                    'bankName' => 'OPAY',
                    'transactionStatus' => 'Success',
                    'contractReference' => '20260526FT00000000000002878665',
                    'creditAccountNumber' => '8133324165',
                    'creditAccountName' => 'CRYSTAL VOCHEDAPWA ISHAKU',
                    'debitAccountName' => 'CRYSTAL ISHAKU',
                    'debitAccountNumber' => '1000005135',
                    'narration' => 'WhatsApp wallet bank transfer',
                    'paymentReference' => '090175260526184248785818618275',
                    'responseCode' => '00',
                    'responseMessage' => 'Success',
                    'sessionId' => 'waw_wdfysny1kurtlx',
                ],
            ], 200),
        ]);

        $service = new MevonPayTransferStatusService;
        $result = $service->checkStatus('waw_wdfysny1kurtlx');

        $this->assertTrue($result['available']);
        $this->assertSame(MavonPayTransferService::BUCKET_SUCCESSFUL, $result['bucket']);
        $this->assertSame('00', $result['response_code']);
        $this->assertSame('Success', $result['transaction_status']);
        $this->assertSame('waw_wdfysny1kurtlx', $result['details']['sessionId'] ?? null);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://mevonpay.com.ng/V1/tsk'
                && $request['reference'] === 'waw_wdfysny1kurtlx'
                && $request->hasHeader('Authorization', 'Bearer secret_test_key');
        });
    }

    public function test_meta_normalizer_maps_details_from_tsk_raw(): void
    {
        $raw = [
            'status' => 'success',
            'message' => 'Transaction status verification complete.',
            'reference' => 'waw_wdfysny1kurtlx',
            'details' => [
                'responseCode' => '00',
                'responseMessage' => 'Success',
                'sessionId' => 'waw_wdfysny1kurtlx',
                'transactionStatus' => 'Success',
            ],
        ];

        $api = \App\Services\MevonPay\MevonPayPayoutMetaNormalizer::extractApiResponse([
            'raw' => $raw,
            'bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
            'response_code' => '00',
            'reference' => 'waw_wdfysny1kurtlx',
        ]);

        $this->assertSame('00', $api['responseCode']);
        $this->assertSame('Success', $api['transactionStatus']);
        $this->assertSame('waw_wdfysny1kurtlx', $api['sessionId']);
    }
}
