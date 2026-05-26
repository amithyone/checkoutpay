<?php

namespace Tests\Unit\MevonPay;

use App\Services\MavonPayTransferService;
use App\Services\MevonPay\MevonPayPayoutMetaNormalizer;
use PHPUnit\Framework\TestCase;

class MevonPayPayoutMetaNormalizerTest extends TestCase
{
    public function test_build_payload_matches_expected_shape(): void
    {
        $result = [
            'bucket' => MavonPayTransferService::BUCKET_FAILED,
            'response_code' => '91',
            'response_message' => 'Beneficiary Institution not available',
            'reference' => 'waw_eyah5tvx9bznim',
            'session_id' => '090175260526161054763910703582',
            'http_status' => 200,
            'curl_error' => '',
            'raw' => [
                'sessionId' => '090175260526161054763910703582',
                'amount' => '4925.00',
                'contractReference' => '20260526FT00000000000002875133',
                'creditAccount' => '9131665268',
                'creditAccountName' => 'ALICIA CHIZETEREM JOSEPH',
                'debitAccountNumber' => '1000003574',
                'narration' => 'WHATSAPP WALLET BANK TRANSFER',
                'reference' => 'waw_eyah5tvx9bznim',
                'responseMessage' => 'Beneficiary Institution not available',
                'responseCode' => '91',
            ],
        ];

        $payload = MevonPayPayoutMetaNormalizer::buildPayload($result, MavonPayTransferService::BUCKET_FAILED, true);

        $this->assertSame('failed', $payload['status']);
        $this->assertSame('Transfer failed. Funds reversed.', $payload['message']);
        $this->assertSame('090175260526161054763910703582', $payload['api_response']['sessionId']);
        $this->assertSame('91', $payload['api_response']['responseCode']);
        $this->assertSame('Beneficiary Institution not available', $payload['api_response']['responseMessage']);
        $this->assertSame('20260526FT00000000000002875133', $payload['api_response']['contractReference']);
        $this->assertSame('', $payload['curl_error']);
    }

    public function test_merge_into_meta_stores_mevonpay_key(): void
    {
        $meta = MevonPayPayoutMetaNormalizer::mergeIntoMeta(['payout_bucket' => 'failed'], [
            'bucket' => 'failed',
            'response_message' => 'Declined',
            'raw' => ['sessionId' => 'abc', 'responseCode' => '91'],
        ]);

        $this->assertSame('abc', $meta['payout_session_id']);
        $this->assertIsArray($meta['mevonpay']);
        $this->assertSame('abc', $meta['mevonpay']['api_response']['sessionId']);
    }
}
