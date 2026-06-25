<?php

namespace Tests\Unit;

use App\Services\MavonPayTransferService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MavonPayTransferServiceTest extends TestCase
{
    public function test_pending_code_with_success_message_is_treated_as_successful(): void
    {
        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'secret',
            'services.mevonpay.debit_account_name' => 'Test',
            'services.mevonpay.debit_account_number' => '0000000000',
            'services.mevonpay.current_password' => 'pass',
        ]);

        Http::fake([
            'https://mevon.test/V1/createtransfer' => Http::response([
                'responseCode' => '09',
                'responseMessage' => 'Transfer successful',
                'status' => true,
            ], 200),
        ]);

        $svc = app(MavonPayTransferService::class);
        $result = $svc->createTransfer([
            'amount' => 1000,
            'bankCode' => '058',
            'bankName' => 'GTBank',
            'creditAccountName' => 'Jane Doe',
            'creditAccountNumber' => '0123456789',
            'narration' => 'test',
            'reference' => 'waw_testref',
            'sessionId' => 'WAWTEST',
        ]);

        $this->assertSame(MavonPayTransferService::BUCKET_SUCCESSFUL, $result['bucket']);
    }

    public function test_transport_timeout_is_pending_not_failed(): void
    {
        config([
            'services.mevonpay.base_url' => 'https://mevon.test',
            'services.mevonpay.secret_key' => 'secret',
            'services.mevonpay.debit_account_name' => 'Test',
            'services.mevonpay.debit_account_number' => '0000000000',
            'services.mevonpay.current_password' => 'pass',
        ]);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
        });

        $svc = app(MavonPayTransferService::class);
        $result = $svc->createTransfer([
            'amount' => 1000,
            'bankCode' => '058',
            'bankName' => 'GTBank',
            'creditAccountName' => 'Jane Doe',
            'creditAccountNumber' => '0123456789',
            'narration' => 'test',
            'reference' => 'waw_timeout_ref',
            'sessionId' => 'WAWTIMEOUT',
        ]);

        $this->assertSame(MavonPayTransferService::BUCKET_PENDING, $result['bucket']);
        $this->assertTrue($result['provider_response_unknown'] ?? false);
    }
}
