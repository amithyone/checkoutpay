<?php

namespace App\Console\Commands;

use App\Models\MevonPayLedgerEntry;
use App\Services\MevonPay\MevonPayBalanceMonitorService;
use App\Services\MevonPay\MevonPayBalanceSnapshotService;
use App\Services\MevonPay\MevonPayLedgerRecorder;
use App\Services\MevonPay\MevonPayReconBaselineService;
use Illuminate\Console\Command;

class MevonPayReconRecomputeImpactsCommand extends Command
{
    protected $signature = 'mevon:recon-recompute-impacts {--dry-run : Show totals without writing}';

    protected $description = 'Rewrite net_mevon_impact / fee columns for all Mevon ledger rows using current wallet math';

    public function handle(
        MevonPayLedgerRecorder $recorder,
        MevonPayReconBaselineService $baseline,
        MevonPayBalanceMonitorService $monitor,
        MevonPayBalanceSnapshotService $snapshot,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $beforeSum = (float) MevonPayLedgerEntry::query()->sum('net_mevon_impact');
        $count = (int) MevonPayLedgerEntry::query()->count();

        $this->info("Ledger rows: {$count}");
        $this->info('Σ net_mevon_impact before: '.number_format($beforeSum, 2));

        $updated = 0;
        MevonPayLedgerEntry::query()
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($recorder, $dryRun, &$updated) {
                foreach ($rows as $entry) {
                    if ($dryRun) {
                        $updated++;

                        continue;
                    }
                    $recorder->recomputeEntryImpacts($entry);
                    $updated++;
                }
            });

        $afterSum = $dryRun
            ? $beforeSum
            : (float) MevonPayLedgerEntry::query()->sum('net_mevon_impact');

        if (! $dryRun) {
            $this->info('Σ net_mevon_impact after: '.number_format($afterSum, 2));
            $this->info('Δ Σ impact: '.number_format($afterSum - $beforeSum, 2));
        } else {
            $this->warn("[dry-run] Would recompute {$updated} rows (Σ after not written).");
        }

        if ($baseline->isActive()) {
            $opening = $baseline->openingBalance();
            $summary = $monitor->summary();
            $expected = (float) ($summary['expected_balance'] ?? ($opening + ($dryRun ? $beforeSum : $afterSum)));
            $this->info('Baseline opening: '.number_format($opening, 2));
            $this->info('Projected expected (post-baseline math): '.number_format((float) ($summary['expected_balance'] ?? $expected), 2));

            $live = $snapshot->forDashboard();
            if (($live['ok'] ?? false) && isset($live['naira_balance'])) {
                $liveBal = (float) $live['naira_balance'];
                $variance = round($liveBal - (float) ($summary['expected_balance'] ?? $expected), 2);
                $this->info('Live NGN: '.number_format($liveBal, 2));
                $this->info('Variance (live − expected): '.number_format($variance, 2));
            }
        } else {
            $this->warn('Recon baseline is not active — expected balance not projected.');
        }

        return self::SUCCESS;
    }
}
