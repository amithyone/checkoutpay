<?php

namespace App\Console\Commands;

use App\Services\WalletImport\FormCsvTier2BatchProvisionService;
use Illuminate\Console\Command;

class ProvisionTier2BatchCommand extends Command
{
    protected $signature = 'wallet:provision-tier2-batch
        {--limit=8 : Max wallets to queue this run (1–50)}
        {--apply : Actually queue Mevon Tier-2 VA creation}
        {--dry-run : Report only (default unless --apply)}
        {--jsonl= : Sterilized JSONL to pick tier_target=2 phones from}';

    protected $description = 'Gradually queue Mevon Tier-2 personal accounts (5–10 per run; safe for cron)';

    public function handle(FormCsvTier2BatchProvisionService $batch): int
    {
        if (! (bool) config('services.mevonpay.tier2_batch_enabled', false)) {
            $this->error('Tier-2 import batch is disabled (WALLET_TIER2_BATCH_ENABLED=false).');
            $this->line('Only users who submit Tier-2 KYC in the app/WhatsApp are queued.');
            $this->line('Set WALLET_TIER2_BATCH_ENABLED=true only for intentional backfill.');

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $apply = (bool) $this->option('apply');
        $jsonl = $this->option('jsonl') ? (string) $this->option('jsonl') : null;

        if (! $apply) {
            $this->info('Dry-run only (pass --apply to queue Mevon).');
        } else {
            $this->warn("APPLY: queueing up to {$limit} Tier-2 VA provision(s).");
        }

        $stats = $batch->run($limit, $apply, $jsonl);

        $this->table(
            ['metric', 'count'],
            [
                ['candidates_in_cohort', $stats['candidates']],
                ['attempted', $stats['attempted']],
                ['queued', $stats['queued']],
                ['skipped_has_va', $stats['skipped_has_va']],
                ['skipped_in_flight', $stats['skipped_in_flight']],
                ['skipped_not_ready', $stats['skipped_not_ready']],
                ['failed', $stats['failed']],
            ]
        );

        foreach (array_slice($stats['details'], 0, 30) as $row) {
            $msg = isset($row['message']) && $row['message'] !== '' ? ' — '.$row['message'] : '';
            $this->line(sprintf(
                '  · %s wallet=%s %s%s',
                $row['phone'],
                $row['wallet_id'] ?? '-',
                $row['result'],
                $msg
            ));
        }

        if (count($stats['details']) > 30) {
            $this->line('  … and '.(count($stats['details']) - 30).' more');
        }

        // Rough ETA helper for ops.
        $remainingHint = max(0, $stats['candidates'] - $stats['skipped_has_va'] - $stats['skipped_in_flight']);
        $perDay = max(1, $limit * 2);
        $days = (int) ceil($remainingHint / $perDay);
        $this->line("At ~{$limit}×2/day, remaining cohort ≈ {$remainingHint} → ~{$days} day(s).");

        return self::SUCCESS;
    }
}
