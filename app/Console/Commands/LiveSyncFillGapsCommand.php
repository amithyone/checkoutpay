<?php

namespace App\Console\Commands;

use App\Services\LiveSync\LiveSyncCursorService;
use App\Services\LiveSync\LiveSyncGenericEngine;
use App\Services\LiveSync\LiveSyncOutboundService;
use App\Services\LiveSync\LiveSyncTransmitterClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Manual additive sync: push rows Contabo does not have yet (never overwrite existing).
 *
 * Uses persisted per-entity cursors so re-runs continue from the last synced id.
 */
class LiveSyncFillGapsCommand extends Command
{
    protected $signature = 'live-sync:fill-gaps
        {--entity=common : Entity name, common, or a single entity (not float)}
        {--since= : Optional lower bound on created_at/updated_at}
        {--cursor= : Override saved cursor (start after this id)}
        {--batch-size=500 : Candidate rows scanned per page}
        {--chunk= : Events per HTTP batch (default from config, max 50)}
        {--until-done : Keep paging until no more candidates}
        {--reset-cursor : Reset saved cursor for selected entity/entities before run}
        {--no-probe : Skip Contabo probe (insert_only batches only; faster backfill)}
        {--dry-run : Probe/count only, do not send}
        {--sync : Send inline (required on Namecheap; ignores LIVE_SYNC_QUEUE)}';

    protected $description = 'Manually push missing rows to Contabo only (insert-only, batch HTTP, persisted cursors)';

    public function handle(
        LiveSyncOutboundService $outbound,
        LiveSyncTransmitterClient $client,
        LiveSyncGenericEngine $engine,
        LiveSyncCursorService $cursors,
    ): int {
        if (! $client->isConfigured() && ! $this->option('dry-run')) {
            $this->error('Transmitter not configured. Run on Namecheap with LIVE_SYNC_TRANSMIT_ENABLED=true.');

            return self::FAILURE;
        }

        if ((bool) $this->option('sync')) {
            config(['services.live_sync.queue' => false]);
        } elseif (! $this->option('dry-run')) {
            $this->warn('Tip: use --sync for immediate batch sends (queue adds latency per row).');
        }

        $entityOpt = strtolower(trim((string) $this->option('entity')));
        $floatSet = $engine->floatEntities();

        if ($entityOpt === 'float') {
            $this->error('fill-gaps is insert-only and skips existing rows. Float/balances need overwrite:');
            $this->line('  php artisan live-sync:push --entity=float --mode=recent --force-all --limit=500 --sync --chunk=25');

            return self::FAILURE;
        }

        $entities = match ($entityOpt) {
            'common', 'all' => array_values(array_diff($engine->commonEntities(), $floatSet)),
            default => [$entityOpt],
        };

        if ($entities === []) {
            $this->error('No entities to sync.');

            return self::FAILURE;
        }

        if ($entityOpt === 'common' || $entityOpt === 'all') {
            $this->info('Skipping float entities (renter, business, whatsapp_wallet). Refresh balances with live-sync:push --entity=float.');
        } elseif (in_array($entityOpt, $floatSet, true)) {
            $this->warn("{$entityOpt}: insert-only adds missing rows only — will not refresh balances on rows Contabo already has.");
        }

        foreach ($entities as $e) {
            try {
                $engine->entityConfig($e);
            } catch (\Throwable) {
                $this->error("Unknown entity: {$e}");

                return self::FAILURE;
            }
        }

        if ((bool) $this->option('reset-cursor')) {
            foreach ($entities as $entity) {
                $cursors->reset($entity);
                $this->info("Reset cursor for {$entity}.");
            }
        }

        $since = null;
        if ($this->option('since')) {
            try {
                $since = Carbon::parse((string) $this->option('since'));
            } catch (\Throwable) {
                $this->error('Invalid --since datetime.');

                return self::FAILURE;
            }
            $this->info('Optional window since: '.$since->toDateTimeString().' UTC');
        }

        $batchSize = max(50, min(2000, (int) $this->option('batch-size')));
        $chunkSize = $this->option('chunk') !== null
            ? max(1, min(50, (int) $this->option('chunk')))
            : max(1, min(50, (int) config('live_sync.batch.chunk_size', 25)));
        $dryRun = (bool) $this->option('dry-run');
        $untilDone = (bool) $this->option('until-done');
        $noProbe = (bool) $this->option('no-probe');
        $cursorOverride = $this->option('cursor') !== null ? max(0, (int) $this->option('cursor')) : null;

        $this->info('fill-gaps · entities='.count($entities)." · batch-size={$batchSize} · chunk={$chunkSize}".($untilDone ? ' · until-done' : ''));

        $totals = ['pushed' => 0, 'skipped_present' => 0, 'fail' => 0, 'candidates' => 0];

        foreach ($entities as $entity) {
            $savedCursor = $cursors->cursorFor($entity);
            $cursor = $cursorOverride ?? (int) $savedCursor->last_origin_id;
            $caughtUp = $savedCursor->isCaughtUp() && $cursorOverride === null;

            $this->info("Entity: {$entity} (cursor > {$cursor}".($caughtUp ? ', caught_up — no probe' : ', backfill').')');

            do {
                $page = $this->scanPage(
                    $outbound,
                    $client,
                    $engine,
                    $entity,
                    $since,
                    $cursor,
                    $batchSize,
                    $chunkSize,
                    $dryRun,
                    $caughtUp || $noProbe,
                );

                $totals['pushed'] += $page['pushed'];
                $totals['skipped_present'] += $page['skipped_present'];
                $totals['fail'] += $page['fail'];
                $totals['candidates'] += $page['candidates'];

                if ($page['fail'] > 0) {
                    $this->error("Stopped {$entity} after batch failure. Resume with --entity={$entity} --cursor={$cursor}");

                    return self::FAILURE;
                }

                if (! $dryRun && $page['next_cursor'] > $cursor) {
                    $cursors->advance($entity, $page['next_cursor'], $page['pushed']);
                }

        if (! $dryRun && ! $page['has_more']) {
                    $maxId = $cursors->maxOriginIdForEntity($entity, $engine);
                    $cursors->markCaughtUp($entity, $maxId > 0 ? $maxId : $page['next_cursor']);
                    $this->line("  {$entity} marked caught_up (cursor={$page['next_cursor']}).");
                }

                $cursor = $page['next_cursor'];
                $hasMore = $page['has_more'];
                if ($page['candidates'] === 0 && ! $page['has_more']) {
                    $caughtUp = true;
                }
            } while ($untilDone && $hasMore);
        }

        $this->info(
            ($dryRun ? '[dry-run] ' : '')
            ."done candidates={$totals['candidates']} pushed={$totals['pushed']} skipped_present={$totals['skipped_present']} fail={$totals['fail']}"
        );

        return $totals['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{pushed: int, skipped_present: int, fail: int, candidates: int, next_cursor: int, has_more: bool}
     */
    private function scanPage(
        LiveSyncOutboundService $outbound,
        LiveSyncTransmitterClient $client,
        LiveSyncGenericEngine $engine,
        string $entity,
        ?Carbon $since,
        int $cursor,
        int $batchSize,
        int $chunkSize,
        bool $dryRun,
        bool $skipProbe,
    ): array {
        /** @var class-string<Model> $class */
        $class = $engine->modelClass($entity);
        $query = $class::query()->where('id', '>', $cursor)->orderBy('id');
        if ($since) {
            $query->where(function ($q) use ($since) {
                $q->where('updated_at', '>=', $since)->orWhere('created_at', '>=', $since);
            });
        }

        $rows = $query->limit($batchSize)->get();
        if ($rows->isEmpty()) {
            $this->line('  No more candidates.');

            return [
                'pushed' => 0,
                'skipped_present' => 0,
                'fail' => 0,
                'candidates' => 0,
                'next_cursor' => $cursor,
                'has_more' => false,
            ];
        }

        $rows->loadMissing($this->eagerRelations($entity));
        $lastId = (int) $rows->last()->getKey();

        $rowsToPush = $rows->all();
        $skippedPresent = 0;

        if (! $skipProbe) {
            $keyMap = [];
            foreach ($rows as $row) {
                $key = $engine->probeKeyLightForModel($entity, $row);
                if ($key !== '') {
                    $keyMap[$key] = $row;
                }
            }

            $probe = $client->probeMissing($entity, array_keys($keyMap));
            if (! ($probe['ok'] ?? false)) {
                $this->warn('  Probe failed: '.($probe['message'] ?? 'unknown').' — falling back to insert-only for this page');

                $rowsToPush = $rows->all();
                $skippedPresent = 0;
            } else {
                $missing = $probe['missing'] ?? [];
                $skippedPresent = count($probe['present'] ?? []);
                $this->line("  id ≤ {$lastId}: candidates {$rows->count()} · present {$skippedPresent} · missing ".count($missing));

                $rowsToPush = [];
                foreach ($missing as $key) {
                    $row = $keyMap[$key] ?? null;
                    if ($row) {
                        $rowsToPush[] = $row;
                    }
                }
            }
        } else {
            $this->line("  id ≤ {$lastId}: candidates {$rows->count()} · incremental (no probe)");
        }

        if ($dryRun) {
            return [
                'pushed' => count($rowsToPush),
                'skipped_present' => $skippedPresent,
                'fail' => 0,
                'candidates' => $rows->count(),
                'next_cursor' => $lastId,
                'has_more' => $rows->count() === $batchSize,
            ];
        }

        $pushed = 0;
        $fail = 0;

        foreach (array_chunk($rowsToPush, $chunkSize) as $chunk) {
            $items = [];
            foreach ($chunk as $row) {
                $items[] = [
                    'entity' => $entity,
                    'operation' => 'upsert',
                    'data' => $engine->serialize($entity, $row),
                ];
            }

            if ($items === []) {
                continue;
            }

            $result = $outbound->pushBatchNow($items, insertOnly: true);
            if ($result['ok'] ?? false) {
                $pushed += (int) ($result['processed'] ?? count($chunk));
                $fail += (int) ($result['failed'] ?? 0);
            } else {
                $fail += count($chunk);
                $this->warn('  Batch failed: '.($result['message'] ?? 'unknown'));
            }
        }

        // Incremental mode: advance through all scanned rows even if receiver skipped them.
        if ($skipProbe && $fail === 0 && $pushed === 0 && $rows->isNotEmpty()) {
            $skippedPresent = $rows->count();
        }

        return [
            'pushed' => $pushed,
            'skipped_present' => $skippedPresent,
            'fail' => $fail,
            'candidates' => $rows->count(),
            'next_cursor' => $lastId,
            'has_more' => $rows->count() === $batchSize,
        ];
    }

    /**
     * @return list<string>
     */
    private function eagerRelations(string $entity): array
    {
        return match ($entity) {
            'whatsapp_wallet' => ['renter', 'linkedBusiness'],
            'business_account_application' => ['wallet', 'linkedBusiness'],
            'business_name_registration' => ['wallet'],
            'whatsapp_wallet_transaction', 'virtual_card_request', 'wallet_savings_setting', 'wallet_savings_goal', 'wallet_savings_lock' => ['wallet'],
            'withdrawal_request', 'business_activity_log', 'business_withdrawal_account', 'business_employee', 'business_disbursement_batch', 'business_website' => ['business'],
            'business_transaction' => ['business', 'payment'],
            'business_disbursement_item' => ['batch'],
            default => [],
        };
    }
}
