<?php

namespace App\Console\Commands;

use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletSavingsService;
use Illuminate\Console\Command;

/**
 * Align whatsapp_wallets.savings_balance / flexible_savings_balance with active lock rows.
 */
class ReconcileSavingsBalancesFromLocksCommand extends Command
{
    protected $signature = 'savings:reconcile-balances-from-locks
                            {--wallet= : Optional whatsapp_wallets.id}
                            {--dry-run : Report mismatches without writing}
                            {--yes : Run without confirmation}';

    protected $description = 'Set wallet savings columns from sum of active locks (fixes personal/business pooled mismatch vs app)';

    public function handle(ConsumerWalletSavingsService $savings): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $walletId = $this->option('wallet');

        $query = WhatsappWallet::query()->orderBy('id');
        if ($walletId !== null && $walletId !== '') {
            $query->whereKey((int) $walletId);
        } else {
            $query->where(function ($q) {
                $q->where('savings_balance', '>', 0)
                    ->orWhere('flexible_savings_balance', '>', 0)
                    ->orWhereHas('savingsLocks', fn ($lq) => $lq->where('status', 'active'));
            });
        }

        $wallets = $query->get();
        if ($wallets->isEmpty()) {
            $this->info('No wallets to check.');

            return self::SUCCESS;
        }

        $this->line('Checking '.$wallets->count().' wallet(s)...');

        if (! $dryRun && ! $this->option('yes') && ! $this->confirm('Reconcile mismatched wallets?', true)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $mismatched = 0;
        $fixed = 0;

        foreach ($wallets as $wallet) {
            $summary = $savings->getSummary($wallet);
            $match = (bool) ($summary['balances_match_locks'] ?? false);
            if ($match) {
                continue;
            }

            $mismatched++;
            $this->warn(sprintf(
                'wallet #%d mismatch — columns locked=%.2f flexible=%.2f | locks strict=%.2f flexible=%.2f (personal strict=%.2f / business strict=%.2f)',
                $wallet->id,
                (float) $summary['savings_balance'],
                (float) $summary['flexible_savings_balance'],
                (float) $summary['locks_strict_total'],
                (float) $summary['locks_flexible_total'],
                (float) $summary['personal_strict_balance'],
                (float) $summary['business_strict_balance'],
            ));

            if ($dryRun) {
                continue;
            }

            $result = $savings->reconcileWalletBalancesFromLocks($wallet);
            if ($result['ok'] ?? false) {
                $fixed++;
                $after = $result['after'] ?? [];
                $this->info(sprintf(
                    '  fixed → savings_balance=%.2f flexible=%.2f',
                    (float) ($after['savings_balance'] ?? 0),
                    (float) ($after['flexible_savings_balance'] ?? 0),
                ));
            } else {
                $this->error('  '.$result['message']);
            }
        }

        if ($dryRun) {
            $this->warn("Dry run: {$mismatched} mismatched wallet(s). No writes.");
        } else {
            $this->info("Done. Mismatched {$mismatched}, fixed {$fixed}.");
        }

        return self::SUCCESS;
    }
}
