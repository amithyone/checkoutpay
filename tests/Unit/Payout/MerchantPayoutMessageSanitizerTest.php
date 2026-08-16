<?php

namespace Tests\Unit\Payout;

use App\Models\WithdrawalRequest;
use App\Services\MavonPayTransferService;
use App\Services\Payout\MerchantPayoutMessageSanitizer;
use Tests\TestCase;

class MerchantPayoutMessageSanitizerTest extends TestCase
{
    public function test_success_and_pending_do_not_use_provider_text(): void
    {
        $sanitizer = new MerchantPayoutMessageSanitizer;

        $ok = new WithdrawalRequest([
            'payout_status' => MavonPayTransferService::BUCKET_SUCCESSFUL,
            'payout_response_message' => 'MevonPay Approved /V1/payout',
        ]);
        $this->assertSame(MerchantPayoutMessageSanitizer::SUCCESS, $sanitizer->forWithdrawal($ok));

        $pending = new WithdrawalRequest([
            'payout_status' => MavonPayTransferService::BUCKET_PENDING,
            'payout_response_message' => 'cURL error 28: timeout to mevonpay',
        ]);
        $this->assertSame(MerchantPayoutMessageSanitizer::PENDING, $sanitizer->forWithdrawal($pending));
    }

    public function test_maps_invalid_account_and_hides_provider_errors(): void
    {
        $sanitizer = new MerchantPayoutMessageSanitizer;

        $this->assertStringContainsString(
            'account number is invalid',
            strtolower($sanitizer->sanitizeFailure('Invalid Account Number from MevonPay NIP')),
        );

        $this->assertSame(
            MerchantPayoutMessageSanitizer::GENERIC_FAILURE,
            $sanitizer->sanitizeFailure('SQLSTATE[HY000] Connection refused at 10.0.0.8'),
        );

        $this->assertSame(
            MerchantPayoutMessageSanitizer::GENERIC_FAILURE,
            $sanitizer->sanitizeFailure('MevonPay unauthorized: invalid Token secret_key'),
        );

        $this->assertSame(
            MerchantPayoutMessageSanitizer::NATIVE_INSUFFICIENT,
            $sanitizer->forNative('failed', 'Insufficient funds on MevonPay debit account'),
        );
        $this->assertSame(
            MerchantPayoutMessageSanitizer::NATIVE_GENERIC,
            $sanitizer->forNative('failed', 'MevonPay unauthorized: invalid Token'),
        );
        $this->assertStringNotContainsStringIgnoringCase(
            'mevon',
            $sanitizer->forNative('failed', 'Invalid Account Number from MevonPay NIP'),
        );
    }
}
