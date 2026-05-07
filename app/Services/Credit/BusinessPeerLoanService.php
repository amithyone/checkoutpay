<?php

namespace App\Services\Credit;

use App\Models\Business;
use App\Models\BusinessLoan;
use App\Models\BusinessLoanLedgerEntry;
use App\Models\BusinessLoanSchedule;
use App\Models\BusinessLendingOffer;
use App\Notifications\PeerLoanRepaymentCollectedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BusinessPeerLoanService
{
    public function computeTotalRepayment(float $principal, float $ratePercent, int $termDays): float
    {
        $interest = round($principal * ($ratePercent / 100) * ($termDays / 365), 2);

        return round($principal + $interest, 2);
    }

    /**
     * @return list<array{due_at: Carbon, amount: float}>
     */
    public function buildScheduleDates(BusinessLendingOffer $offer, float $totalRepayment, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? now();

        if ($offer->repayment_type === BusinessLendingOffer::REPAYMENT_LUMP) {
            return [[
                'due_at' => $asOf->copy()->addDays($offer->term_days),
                'amount' => $totalRepayment,
            ]];
        }

        $weeks = max(1, (int) ceil($offer->term_days / 7));
        $base = round($totalRepayment / $weeks, 2);
        $out = [];
        for ($i = 1; $i <= $weeks; $i++) {
            $amt = $i === $weeks ? round($totalRepayment - $base * ($weeks - 1), 2) : $base;
            $out[] = [
                'due_at' => $asOf->copy()->addDays(7 * $i),
                'amount' => $amt,
            ];
        }

        return $out;
    }

    public function createSchedules(BusinessLoan $loan): void
    {
        $offer = $loan->offer;
        $asOf = $loan->disbursed_at ?? now();
        $rows = $this->buildScheduleDates($offer, (float) $loan->total_repayment, $asOf);
        $seq = 1;
        foreach ($rows as $row) {
            BusinessLoanSchedule::create([
                'business_loan_id' => $loan->id,
                'sequence' => $seq++,
                'due_at' => $row['due_at'],
                'amount_due' => $row['amount'],
                'amount_paid' => 0,
                'status' => 'pending',
            ]);
        }
    }

    public function disburse(BusinessLoan $loan): void
    {
        $loan->load(['offer.lender', 'borrower', 'schedules']);

        if ($loan->status !== BusinessLoan::STATUS_PENDING_ADMIN) {
            throw new \RuntimeException('Loan is not awaiting disbursement.');
        }
        if ($loan->schedules->isNotEmpty()) {
            throw new \RuntimeException('Loan already has schedules.');
        }

        $offer = $loan->offer;
        $lender = $offer->lender;
        $borrower = $loan->borrower;

        if ($offer->status !== BusinessLendingOffer::STATUS_ACTIVE) {
            throw new \RuntimeException('Offer is not active.');
        }
        if ((float) $lender->balance < (float) $loan->principal) {
            throw new \RuntimeException('Lender has insufficient balance.');
        }

        DB::transaction(function () use ($loan, $lender, $borrower, $offer) {
            $lenderLocked = Business::query()->whereKey($lender->id)->lockForUpdate()->firstOrFail();
            $borrowerLocked = Business::query()->whereKey($borrower->id)->lockForUpdate()->firstOrFail();

            if ((float) $lenderLocked->balance < (float) $loan->principal) {
                throw new \RuntimeException('Lender has insufficient balance.');
            }

            $lenderLocked->decrement('balance', $loan->principal);
            $borrowerLocked->increment('balance', $loan->principal);

            $loan->update([
                'status' => BusinessLoan::STATUS_ACTIVE,
                'disbursed_at' => now(),
            ]);

            $this->createSchedules($loan->fresh());

            BusinessLoanLedgerEntry::create([
                'business_loan_id' => $loan->id,
                'entry_type' => BusinessLoanLedgerEntry::TYPE_DISBURSEMENT,
                'amount' => $loan->principal,
                'from_business_id' => $lenderLocked->id,
                'to_business_id' => $borrowerLocked->id,
                'metadata' => ['offer_id' => $offer->id],
            ]);
        });

        Log::info('Peer loan disbursed', ['loan_id' => $loan->id]);
    }

    /**
     * Collect due installments from borrower positive balance; credit lender.
     */
    public function collectDue(): int
    {
        $count = 0;

        BusinessLoanSchedule::query()
            ->where('status', 'pending')
            ->where('due_at', '<', now())
            ->whereHas('loan', function ($q) {
                $q->where('status', BusinessLoan::STATUS_ACTIVE);
            })
            ->whereRaw('amount_paid < amount_due')
            ->update(['status' => 'overdue']);

        $schedules = BusinessLoanSchedule::query()
            ->whereIn('status', ['pending', 'overdue'])
            ->whereHas('loan', function ($q) {
                $q->where('status', BusinessLoan::STATUS_ACTIVE);
            })
            ->orderBy('due_at')
            ->orderBy('id')
            ->with(['loan.borrower', 'loan.offer.lender'])
            ->get();

        foreach ($schedules as $schedule) {
            $loan = $schedule->loan;
            $borrower = $loan->borrower;
            $lender = $loan->offer->lender;
            $remaining = $schedule->remaining();
            if ($remaining < 0.01) {
                $schedule->update(['status' => 'paid']);
                continue;
            }

            $paidThisRun = 0.0;

            DB::transaction(function () use ($schedule, $loan, $borrower, $lender, $remaining, &$count, &$paidThisRun) {
                $b = Business::query()->whereKey($borrower->id)->lockForUpdate()->firstOrFail();
                $available = max(0, (float) $b->balance);
                if ($available < 0.01) {
                    return;
                }

                $pay = round(min($remaining, $available), 2);
                if ($pay < 0.01) {
                    return;
                }
                $b->decrement('balance', $pay);

                $lenderLocked = Business::query()->whereKey($lender->id)->lockForUpdate()->firstOrFail();
                $lenderLocked->increment('balance', $pay);

                $newPaid = round((float) $schedule->amount_paid + $pay, 2);
                $paidFull = $newPaid + 0.0001 >= (float) $schedule->amount_due;
                $schedule->update([
                    'amount_paid' => $newPaid,
                    'status' => $paidFull ? 'paid' : $schedule->status,
                ]);

                BusinessLoanLedgerEntry::create([
                    'business_loan_id' => $loan->id,
                    'entry_type' => BusinessLoanLedgerEntry::TYPE_COLLECTION,
                    'amount' => $pay,
                    'from_business_id' => $b->id,
                    'to_business_id' => $lenderLocked->id,
                    'metadata' => ['schedule_id' => $schedule->id],
                ]);

                $count++;
                $paidThisRun = $pay;
            });

            if ($paidThisRun >= 0.01) {
                $this->notifyRepaymentCollected($loan, $schedule, $paidThisRun);
            }

            $loan->refresh()->load('schedules');
            $allPaid = $loan->schedules->every(fn ($s) => $s->status === 'paid');
            if ($allPaid) {
                $loan->update([
                    'status' => BusinessLoan::STATUS_REPAID,
                    'repaid_at' => now(),
                ]);
            }
        }

        return $count;
    }

    private function notifyRepaymentCollected(BusinessLoan $loan, BusinessLoanSchedule $schedule, float $amountCollected): void
    {
        $loan = $loan->fresh(['offer.lender', 'borrower', 'schedules']);
        $schedule = $schedule->fresh();
        if (! $loan || ! $schedule) {
            return;
        }

        $borrower = $loan->borrower;
        $lender = $loan->offer?->lender;
        $remainingOnSchedule = max(0, round((float) $schedule->amount_due - (float) $schedule->amount_paid, 2));
        $remainingOnLoan = round(
            $loan->schedules->sum(fn ($s) => max(0, (float) $s->amount_due - (float) $s->amount_paid)),
            2
        );

        try {
            if ($borrower) {
                $borrower->notify(new PeerLoanRepaymentCollectedNotification(
                    $loan,
                    $schedule,
                    $amountCollected,
                    $remainingOnSchedule,
                    $remainingOnLoan,
                    PeerLoanRepaymentCollectedNotification::ROLE_BORROWER
                ));
            }
            if ($lender) {
                $lender->notify(new PeerLoanRepaymentCollectedNotification(
                    $loan,
                    $schedule,
                    $amountCollected,
                    $remainingOnSchedule,
                    $remainingOnLoan,
                    PeerLoanRepaymentCollectedNotification::ROLE_LENDER
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Peer loan repayment notification failed', [
                'loan_id' => $loan->id,
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
