<?php

namespace Tests\Unit\Consumer;

use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletTransactionStatusNormalizer;
use App\Services\MavonPayTransferService;
use PHPUnit\Framework\TestCase;

class ConsumerWalletTransactionStatusNormalizerTest extends TestCase
{
    private ConsumerWalletTransactionStatusNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ConsumerWalletTransactionStatusNormalizer;
    }

    public function test_pending_bank_transfer_exposes_status_fields_app_reads(): void
    {
        $row = $this->normalizer->apply([
            'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
            'amount' => '1000.00',
            'meta' => [
                'payout_pending' => true,
                'payout_bucket' => MavonPayTransferService::BUCKET_PENDING,
                'payout_failed' => false,
            ],
        ]);

        $this->assertSame('pending', $row['status']);
        $this->assertSame('pending', $row['state']);
        $this->assertFalse($row['failed']);
        $this->assertFalse($row['payout_failed']);
        $this->assertSame('pending', $row['meta']['status']);
        $this->assertSame('pending', $row['meta']['payout_status']);
        $this->assertSame('pending', $row['meta']['transfer_status']);
        $this->assertTrue($row['meta']['payout_pending']);
    }

    public function test_failed_bank_transfer_sets_failed_flags(): void
    {
        $row = $this->normalizer->apply([
            'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
            'meta' => [
                'payout_bucket' => MavonPayTransferService::BUCKET_FAILED,
                'payout_failed' => true,
            ],
        ]);

        $this->assertSame('failed', $row['status']);
        $this->assertTrue($row['failed']);
        $this->assertTrue($row['payout_failed']);
        $this->assertTrue($row['meta']['failed']);
        $this->assertTrue($row['meta']['payout_failed']);
        $this->assertSame('failed', $row['meta']['status']);
    }

    public function test_successful_bank_transfer_defaults_to_success(): void
    {
        $row = $this->normalizer->apply([
            'type' => WhatsappWalletTransaction::TYPE_BANK_TRANSFER_OUT,
            'meta' => [
                'payout_bucket' => MavonPayTransferService::BUCKET_SUCCESSFUL,
            ],
        ]);

        $this->assertSame('success', $row['status']);
        $this->assertFalse($row['failed']);
        $this->assertSame('success', $row['meta']['status']);
    }

    public function test_merchant_payment_keeps_approved_token(): void
    {
        $row = $this->normalizer->apply([
            'type' => 'merchant_payment_in',
            'meta' => ['status' => 'approved'],
        ]);

        $this->assertSame('approved', $row['status']);
        $this->assertSame('approved', $row['meta']['status']);
        $this->assertFalse($row['failed']);
    }

    public function test_merchant_pending_payment_stays_pending(): void
    {
        $row = $this->normalizer->apply([
            'type' => 'merchant_payment_in',
            'meta' => ['status' => 'pending'],
        ]);

        $this->assertSame('pending', $row['status']);
        $this->assertFalse($row['failed']);
    }

    public function test_business_rubies_in_gets_approved_status(): void
    {
        $row = $this->normalizer->apply([
            'type' => WhatsappWalletTransaction::TYPE_BUSINESS_RUBIES_IN,
            'meta' => ['payment_id' => 1],
        ]);

        $this->assertSame('approved', $row['status']);
        $this->assertSame('approved', $row['meta']['status']);
    }

    public function test_vtu_pending_maps_to_pending(): void
    {
        $row = $this->normalizer->apply([
            'type' => WhatsappWalletTransaction::TYPE_VTU_AIRTIME,
            'meta' => ['vtu_pending' => true],
        ]);

        $this->assertSame('pending', $row['status']);
        $this->assertSame('pending', $row['meta']['status']);
    }
}
