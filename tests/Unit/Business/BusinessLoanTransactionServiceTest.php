<?php

namespace Tests\Unit\Business;

use App\Models\Business;
use App\Models\BusinessLendingOffer;
use App\Models\BusinessLoan;
use App\Models\BusinessLoanLedgerEntry;
use App\Models\BusinessTransaction;
use App\Services\Business\BusinessLoanTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessLoanTransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_repayment_creates_rows_for_borrower_and_lender(): void
    {
        $lender = $this->makeBusiness('Lender Co');
        $borrower = $this->makeBusiness('Borrower Co');

        $offer = BusinessLendingOffer::query()->create([
            'lender_business_id' => $lender->id,
            'amount' => 10000,
            'interest_rate_percent' => 10,
            'term_days' => 30,
            'repayment_type' => BusinessLendingOffer::REPAYMENT_LUMP,
            'status' => BusinessLendingOffer::STATUS_ACTIVE,
            'public_slug' => 'offer-test-1',
        ]);

        $loan = BusinessLoan::query()->create([
            'business_lending_offer_id' => $offer->id,
            'borrower_business_id' => $borrower->id,
            'principal' => 10000,
            'total_repayment' => 11000,
            'status' => BusinessLoan::STATUS_ACTIVE,
            'disbursed_at' => now(),
        ]);

        $entry = BusinessLoanLedgerEntry::query()->create([
            'business_loan_id' => $loan->id,
            'entry_type' => BusinessLoanLedgerEntry::TYPE_COLLECTION,
            'amount' => 1500,
            'from_business_id' => $borrower->id,
            'to_business_id' => $lender->id,
            'metadata' => ['schedule_id' => 1],
        ]);

        app(BusinessLoanTransactionService::class)->recordRepayment($entry);

        $borrowerTx = BusinessTransaction::query()
            ->where('business_id', $borrower->id)
            ->where('business_loan_ledger_entry_id', $entry->id)
            ->first();
        $lenderTx = BusinessTransaction::query()
            ->where('business_id', $lender->id)
            ->where('business_loan_ledger_entry_id', $entry->id)
            ->first();

        $this->assertNotNull($borrowerTx);
        $this->assertNotNull($lenderTx);
        $this->assertSame(BusinessLoanTransactionService::TYPE_REPAYMENT_OUT, $borrowerTx->type);
        $this->assertSame(BusinessLoanTransactionService::TYPE_REPAYMENT_IN, $lenderTx->type);
        $this->assertEquals(1500.0, (float) $borrowerTx->amount);
        $this->assertEquals(1500.0, (float) $lenderTx->amount);
        $this->assertSame((int) $lender->id, (int) $borrowerTx->counterparty_business_id);
        $this->assertSame((int) $borrower->id, (int) $lenderTx->counterparty_business_id);
        $this->assertStringContainsString('Lender Co', (string) $borrowerTx->description);
        $this->assertStringContainsString('Borrower Co', (string) $lenderTx->description);
    }

    private function makeBusiness(string $name): Business
    {
        return Business::query()->create([
            'name' => $name,
            'email' => Str::lower(str_replace(' ', '-', $name)).'@example.test',
            'password' => Hash::make('password'),
            'balance' => 0,
            'is_active' => true,
        ]);
    }
}
