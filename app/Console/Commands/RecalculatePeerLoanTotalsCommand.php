<?php

namespace App\Console\Commands;

use App\Models\BusinessLoan;
use App\Models\BusinessLoanLedgerEntry;
use App\Services\Credit\BusinessPeerLoanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculatePeerLoanTotalsCommand extends Command
{
    protected $signature = 'peer-loans:recalculate-totals
                            {--apply : Write changes (default is dry-run only)}
                            {--force : With --apply, skip confirmation prompt}
                            {--include-active-without-payments : Also fix active loans that have never had a collection (rebuilds schedules)}';

    protected $description = 'Recompute total_repayment using current flat-interest rules for loans created under the old annualised formula';

    public function handle(BusinessPeerLoanService $loanService): int
    {
        $apply = (bool) $this->option('apply');
        $includeActive = (bool) $this->option('include-active-without-payments');

        if (! $apply) {
            $this->warn('Dry run: no database changes. Add --apply (and usually --force in CI) to persist.');
        }

        if ($apply && ! $this->option('force') && ! $this->confirm('This updates loan rows (and may delete/rebuild schedules for active zero-payment loans). Continue?', false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $pendingUpdated = $this->processPendingLoans($loanService, $apply);
        $activeUpdated = 0;
        if ($includeActive) {
            $activeUpdated = $this->processActiveLoansWithoutCollections($loanService, $apply);
        } else {
            $this->line('Skipped active loans (pass --include-active-without-payments to consider them).');
        }

        $mode = $apply ? 'Applied' : 'Dry run';
        $this->info("{$mode}: {$pendingUpdated} pending loan(s), {$activeUpdated} active loan(s) with zero collections.");

        return self::SUCCESS;
    }

    private function processPendingLoans(BusinessPeerLoanService $loanService, bool $apply): int
    {
        $updated = 0;
        $loans = BusinessLoan::query()
            ->where('status', BusinessLoan::STATUS_PENDING_ADMIN)
            ->with('offer')
            ->orderBy('id')
            ->get();

        foreach ($loans as $loan) {
            $offer = $loan->offer;
            if (! $offer) {
                $this->warn("Loan #{$loan->id}: missing offer, skipped.");

                continue;
            }
            $newTotal = $loanService->computeTotalRepayment(
                (float) $loan->principal,
                (float) $offer->interest_rate_percent
            );
            $oldTotal = (float) $loan->total_repayment;
            if (abs($oldTotal - $newTotal) < 0.005) {
                continue;
            }

            $this->line("Loan #{$loan->id} (pending): total_repayment {$oldTotal} → {$newTotal} (principal {$loan->principal}, rate {$offer->interest_rate_percent}%)");
            $updated++;
            if ($apply) {
                $loan->update(['total_repayment' => $newTotal]);
            }
        }

        return $updated;
    }

    private function processActiveLoansWithoutCollections(BusinessPeerLoanService $loanService, bool $apply): int
    {
        $updated = 0;
        $loans = BusinessLoan::query()
            ->where('status', BusinessLoan::STATUS_ACTIVE)
            ->whereDoesntHave('ledgerEntries', function ($q) {
                $q->where('entry_type', BusinessLoanLedgerEntry::TYPE_COLLECTION);
            })
            ->with(['offer', 'schedules'])
            ->orderBy('id')
            ->get();

        foreach ($loans as $loan) {
            $offer = $loan->offer;
            if (! $offer) {
                $this->warn("Loan #{$loan->id}: missing offer, skipped.");

                continue;
            }
            $paidOnSchedules = (float) $loan->schedules->sum(fn ($s) => (float) $s->amount_paid);
            if ($paidOnSchedules > 0.01) {
                continue;
            }

            $newTotal = $loanService->computeTotalRepayment(
                (float) $loan->principal,
                (float) $offer->interest_rate_percent
            );
            $oldTotal = (float) $loan->total_repayment;
            if (abs($oldTotal - $newTotal) < 0.005) {
                continue;
            }

            $this->line("Loan #{$loan->id} (active, no collections): total_repayment {$oldTotal} → {$newTotal}; schedules will be rebuilt ({$loan->schedules->count()} row(s))");
            $updated++;
            if ($apply) {
                DB::transaction(function () use ($loan, $loanService, $newTotal) {
                    $loan->schedules()->delete();
                    $loan->update(['total_repayment' => $newTotal]);
                    $loanService->createSchedules($loan->fresh(['offer']));
                });
            }
        }

        return $updated;
    }
}
