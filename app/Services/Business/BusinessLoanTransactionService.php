<?php

namespace App\Services\Business;

use App\Models\Business;
use App\Models\BusinessLoanLedgerEntry;
use App\Models\BusinessTransaction;

final class BusinessLoanTransactionService
{
    public const TYPE_REPAYMENT_OUT = 'loan_repayment_out';

    public const TYPE_REPAYMENT_IN = 'loan_repayment_in';

    /**
     * Mirror a peer-loan collection on both borrower and lender transaction histories.
     */
    public function recordRepayment(BusinessLoanLedgerEntry $entry): void
    {
        if ($entry->entry_type !== BusinessLoanLedgerEntry::TYPE_COLLECTION) {
            return;
        }

        if ($entry->from_business_id === null || $entry->to_business_id === null) {
            return;
        }

        $entry->loadMissing(['loan.borrower', 'loan.offer.lender']);
        $borrower = Business::query()->find($entry->from_business_id);
        $lender = Business::query()->find($entry->to_business_id);
        if (! $borrower || ! $lender) {
            return;
        }

        $amount = round((float) $entry->amount, 2);
        if ($amount < 0.01) {
            return;
        }

        $reference = $this->referenceFor($entry);
        $occurredAt = $entry->created_at ?? now();

        $this->upsertForBusiness(
            businessId: (int) $borrower->id,
            ledgerEntryId: (int) $entry->id,
            type: self::TYPE_REPAYMENT_OUT,
            amount: $amount,
            counterpartyBusinessId: (int) $lender->id,
            reference: $reference,
            description: 'Loan repayment to '.$this->businessLabel($lender).' (Loan #'.$entry->business_loan_id.')',
            occurredAt: $occurredAt,
        );

        $this->upsertForBusiness(
            businessId: (int) $lender->id,
            ledgerEntryId: (int) $entry->id,
            type: self::TYPE_REPAYMENT_IN,
            amount: $amount,
            counterpartyBusinessId: (int) $borrower->id,
            reference: $reference,
            description: 'Loan repayment received from '.$this->businessLabel($borrower).' (Loan #'.$entry->business_loan_id.')',
            occurredAt: $occurredAt,
        );
    }

    /**
     * Backfill transaction rows for historical loan collections.
     */
    public function backfillMissingRepayments(): int
    {
        $count = 0;

        BusinessLoanLedgerEntry::query()
            ->where('entry_type', BusinessLoanLedgerEntry::TYPE_COLLECTION)
            ->orderBy('id')
            ->chunkById(100, function ($entries) use (&$count) {
                foreach ($entries as $entry) {
                    $before = BusinessTransaction::query()
                        ->where('business_loan_ledger_entry_id', $entry->id)
                        ->count();

                    $this->recordRepayment($entry);

                    $after = BusinessTransaction::query()
                        ->where('business_loan_ledger_entry_id', $entry->id)
                        ->count();

                    if ($after > $before) {
                        $count += ($after - $before);
                    }
                }
            });

        return $count;
    }

    private function upsertForBusiness(
        int $businessId,
        int $ledgerEntryId,
        string $type,
        float $amount,
        int $counterpartyBusinessId,
        string $reference,
        string $description,
        \DateTimeInterface $occurredAt,
    ): void {
        BusinessTransaction::query()->updateOrCreate(
            [
                'business_id' => $businessId,
                'business_loan_ledger_entry_id' => $ledgerEntryId,
            ],
            [
                'payment_id' => null,
                'amount' => $amount,
                'type' => $type,
                'status' => 'completed',
                'reference' => $reference,
                'description' => $description,
                'counterparty_business_id' => $counterpartyBusinessId,
                'transaction_date' => $occurredAt,
            ]
        );
    }

    private function referenceFor(BusinessLoanLedgerEntry $entry): string
    {
        return 'LOAN-REP-'.$entry->business_loan_id.'-'.$entry->id;
    }

    private function businessLabel(Business $business): string
    {
        $name = trim((string) ($business->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        return 'Business #'.$business->id;
    }
}
