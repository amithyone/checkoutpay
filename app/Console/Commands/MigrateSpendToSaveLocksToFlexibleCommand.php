<?php

namespace App\Console\Commands;

use App\Models\WalletSavingsLock;
use App\Models\WhatsappWallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-time fix: move historically locked spend-to-save deposits into flexible savings.
 */
class MigrateSpendToSaveLocksToFlexibleCommand extends Command
{
    protected $signature = 'savings:migrate-spend-to-save-to-flexible
                            {--dry-run : Show what would change without writing}
                            {--yes : Run without interactive confirmation}';

    protected $description = 'Move active locked spend-to-save balances into flexible savings (one-time mismatch fix)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $locks = WalletSavingsLock::query()
            ->where('source', WalletSavingsLock::SOURCE_SPEND_TO_SAVE)
            ->where('lock_type', WalletSavingsLock::LOCK_TYPE_LOCKED)
            ->where('status', WalletSavingsLock::STATUS_ACTIVE)
            ->orderBy('id')
            ->get();

        if ($locks->isEmpty()) {
            $this->info('No active locked spend-to-save rows to migrate.');

            return self::SUCCESS;
        }

        $totalAmount = round((float) $locks->sum(fn (WalletSavingsLock $l) => (float) $l->amount), 2);
        $walletCount = $locks->pluck('whatsapp_wallet_id')->unique()->count();

        $this->line("Found {$locks->count()} lock(s) across {$walletCount} wallet(s), total ₦".number_format($totalAmount, 2));

        if ($dryRun) {
            foreach ($locks->groupBy('whatsapp_wallet_id') as $walletId => $walletLocks) {
                $sum = round((float) $walletLocks->sum(fn (WalletSavingsLock $l) => (float) $l->amount), 2);
                $this->line("  wallet #{$walletId}: {$walletLocks->count()} lock(s), ₦".number_format($sum, 2));
            }
            $this->warn('Dry run only — no changes written.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('Move these locked spend-to-save amounts into flexible savings?', true)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $migratedLocks = 0;
        $migratedWallets = 0;
        $skipped = 0;

        foreach ($locks->groupBy('whatsapp_wallet_id') as $walletId => $walletLocks) {
            $moved = DB::transaction(function () use ($walletId, $walletLocks, &$migratedLocks, &$skipped) {
                $wallet = WhatsappWallet::query()->lockForUpdate()->find($walletId);
                if (! $wallet) {
                    $skipped += $walletLocks->count();

                    return 0.0;
                }

                $moveTotal = 0.0;

                foreach ($walletLocks as $lock) {
                    /** @var WalletSavingsLock $lock */
                    $fresh = WalletSavingsLock::query()->lockForUpdate()->find($lock->id);
                    if (
                        ! $fresh
                        || $fresh->source !== WalletSavingsLock::SOURCE_SPEND_TO_SAVE
                        || $fresh->lock_type !== WalletSavingsLock::LOCK_TYPE_LOCKED
                        || $fresh->status !== WalletSavingsLock::STATUS_ACTIVE
                    ) {
                        $skipped++;

                        continue;
                    }

                    $amount = round((float) $fresh->amount, 2);
                    if ($amount <= 0) {
                        $skipped++;

                        continue;
                    }

                    $meta = is_array($fresh->meta) ? $fresh->meta : [];
                    $meta['migrated_to_flexible_at'] = now()->toIso8601String();
                    $meta['migrated_from_lock_type'] = WalletSavingsLock::LOCK_TYPE_LOCKED;
                    $meta['migrated_from_matures_at'] = $fresh->matures_at?->toIso8601String();
                    $meta['migrated_from_interest_rate_percent'] = (float) $fresh->interest_rate_percent;

                    $fresh->update([
                        'lock_type' => WalletSavingsLock::LOCK_TYPE_FLEXIBLE,
                        'interest_rate_percent' => 0,
                        'interest_amount' => 0,
                        'matures_at' => null,
                        'meta' => $meta,
                    ]);

                    $moveTotal = round($moveTotal + $amount, 2);
                    $migratedLocks++;
                }

                if ($moveTotal <= 0) {
                    return 0.0;
                }

                $lockedBal = round((float) $wallet->savings_balance, 2);
                $fromLocked = min($lockedBal, $moveTotal);
                $wallet->savings_balance = max(0, round($lockedBal - $fromLocked, 2));
                $wallet->flexible_savings_balance = round((float) $wallet->flexible_savings_balance + $moveTotal, 2);
                $wallet->save();

                if ($fromLocked + 0.0001 < $moveTotal) {
                    Log::warning('savings.spend_to_save_migrate_locked_balance_short', [
                        'wallet_id' => $wallet->id,
                        'move_total' => $moveTotal,
                        'taken_from_savings_balance' => $fromLocked,
                    ]);
                }

                return $moveTotal;
            });

            if ($moved > 0) {
                $migratedWallets++;
                $this->line("  wallet #{$walletId}: moved ₦".number_format($moved, 2)." to flexible");
            }
        }

        Log::info('savings.spend_to_save_migrated_to_flexible', [
            'locks' => $migratedLocks,
            'wallets' => $migratedWallets,
            'skipped' => $skipped,
        ]);

        $this->info("Done. Migrated {$migratedLocks} lock(s) on {$migratedWallets} wallet(s). Skipped {$skipped}.");

        return self::SUCCESS;
    }
}
