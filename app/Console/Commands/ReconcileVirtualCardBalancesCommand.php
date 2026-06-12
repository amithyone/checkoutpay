<?php

namespace App\Console\Commands;

use App\Models\VirtualCardRequest;
use App\Services\Consumer\ConsumerVirtualCardService;
use Illuminate\Console\Command;

class ReconcileVirtualCardBalancesCommand extends Command
{
    protected $signature = 'virtual-card:reconcile-balances
        {--dry-run : Preview balance changes without saving}
        {--request-id= : Only reconcile this virtual_card_requests.id}
        {--wallet-id= : Only reconcile cards for this whatsapp_wallet_id}';

    protected $description = 'Backfill virtual card balances using Mevon provider balance minus confirmed spend history';

    public function handle(ConsumerVirtualCardService $cards): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = VirtualCardRequest::query()
            ->whereNotNull('card_external_id')
            ->where('card_external_id', '!=', '')
            ->whereIn('status', [
                VirtualCardRequest::STATUS_SUBMITTED,
                VirtualCardRequest::STATUS_ACTIVE,
            ]);

        if ($this->option('request-id')) {
            $query->where('id', (int) $this->option('request-id'));
        }

        if ($this->option('wallet-id')) {
            $query->where('whatsapp_wallet_id', (int) $this->option('wallet-id'));
        }

        $rows = $query->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $this->warn('No virtual card requests matched.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Dry run — no database updates will be made.');
        }

        $updated = 0;
        $unchanged = 0;
        $failed = 0;

        foreach ($rows as $row) {
            if ($dryRun) {
                $preview = $cards->previewCardBalanceReconciliation($row);
                if (! ($preview['ok'] ?? false)) {
                    $failed++;
                    $this->warn("Request #{$row->id}: {$preview['message']}");

                    continue;
                }

                $current = $preview['current_balance_usd'];
                $final = $preview['final_balance_usd'];
                $changed = $current === null || abs((float) $current - (float) $final) >= 0.01
                    || (bool) $row->reconciliation_pending !== (bool) ($preview['reconciliation_pending'] ?? false);

                if ($changed) {
                    $updated++;
                    $this->line(sprintf(
                        'Request #%d: $%s -> $%.2f (provider $%.2f, reconciled $%.2f%s)',
                        $row->id,
                        $current === null ? 'null' : number_format((float) $current, 2),
                        (float) $final,
                        (float) $preview['provider_balance_usd'],
                        (float) $preview['reconciled_balance_usd'],
                        ($preview['reconciliation_pending'] ?? false) ? ', pending' : '',
                    ));
                } else {
                    $unchanged++;
                    $this->line("Request #{$row->id}: already correct at \$".number_format((float) $final, 2));
                }

                continue;
            }

            $result = $cards->reconcileCardBalance($row);
            if (! ($result['ok'] ?? false)) {
                $failed++;
                $this->warn("Request #{$row->id}: ".($result['message'] ?? 'Reconciliation failed.'));

                continue;
            }

            $before = (float) ($result['before']['card_balance_usd'] ?? 0);
            $after = $result['after']['card_balance_usd'];
            $afterValue = $after === null ? null : (float) $after;
            $changed = $afterValue === null
                || abs($before - $afterValue) >= 0.01
                || (bool) ($result['before']['reconciliation_pending'] ?? false) !== (bool) ($result['after']['reconciliation_pending'] ?? false);

            if ($changed) {
                $updated++;
                $this->line(sprintf(
                    'Request #%d: $%.2f -> $%.2f%s',
                    $row->id,
                    $before,
                    $afterValue ?? 0.0,
                    ($result['after']['reconciliation_pending'] ?? false) ? ' (pending)' : '',
                ));
            } else {
                $unchanged++;
                $this->line("Request #{$row->id}: unchanged at \$".number_format($afterValue ?? 0.0, 2));
            }
        }

        $this->info("Done. {$updated} updated, {$unchanged} unchanged, {$failed} failed (of {$rows->count()} card(s)).");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
