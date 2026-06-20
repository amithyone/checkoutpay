<?php

namespace Tests\Unit\Consumer;

use App\Services\Consumer\ConsumerAccountStatementBuilder;
use PHPUnit\Framework\TestCase;

class ConsumerAccountStatementBuilderTest extends TestCase
{
    public function test_statement_csv_matches_shared_shape(): void
    {
        $transactions = [
            [
                'type' => 'topup',
                'amount' => 1500,
                'created_at' => '2026-06-15T10:00:00+01:00',
                'meta' => ['label' => 'Bank transfer in'],
            ],
            [
                'type' => 'bank_transfer_out',
                'amount' => 500,
                'created_at' => '2026-06-14T12:00:00+01:00',
                'meta' => ['narration' => 'Rent'],
            ],
        ];

        $csv = ConsumerAccountStatementBuilder::statementCsvContent([
            'ledger_label' => 'Personal wallet',
            'period_label' => 'Last 6 months',
            'from' => '2025-12-20',
            'to' => '2026-06-20',
            'phone' => '2348012345678',
            'account_name' => 'Ada',
            'currency' => 'NGN',
        ], $transactions);

        $this->assertStringContainsString('CheckoutNow Account Statement', $csv);
        $this->assertStringContainsString('Money in,1500', $csv);
        $this->assertStringContainsString('Money out,500', $csv);
        $this->assertStringContainsString('Date,Description,Type,Direction,Amount', $csv);
        $this->assertStringContainsString('Bank transfer in', $csv);
    }

    public function test_mask_email(): void
    {
        $this->assertSame('a***@example.com', ConsumerAccountStatementBuilder::maskEmail('ada@example.com'));
    }
}
