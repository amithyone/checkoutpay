<?php

namespace App\Console\Commands;

use App\Services\LiveSync\LiveSyncGenericEngine;
use App\Services\LiveSync\LiveSyncOutboundService;
use App\Services\LiveSync\LiveSyncTransmitterClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Manual additive sync: push rows Contabo does not have yet (never overwrite existing).
 *
 * Faster than live-sync:push because it uses batch HTTP and id-cursor pagination.
 */
class LiveSyncFillGapsCommand extends Command
{
    protected $signature = 'live-sync:fill-gaps
        {--entity=common : Entity name, common, or a single entity (not float)}
        {--since= : Optional lower bound on created_at/updated_at}
        {--cursor=0 : Start after this primary key id (resume a previous run)}
        {--batch-size=500 : Candidate rows scanned per page}
        {--chunk= : Events per HTTP batch (default from config, max 50)}
        {--until-done : Keep paging until no more candidates}
        {--dry-run : Probe/count only, do not send}
        {--sync : Send inline (required on Namecheap; ignores LIVE_SYNC_QUEUE)}';

    protected $description = 'Manually push missing rows to Contabo only (insert-only, batch HTTP, cursor pagination)';

    public function handle(
        LiveSyncOutboundService $outbound,
        LiveSyncTransmitterClient $client,
        LiveSyncGenericEngine $engine,
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
        $entities = match ($entityOpt) {
            'common', 'all' => $engine->commonEntities(),
            default => [$entityOpt],
        };

        $floatSet = $engine->floatEntities();
        if ($entityOpt === 'float' || count(array_intersect($entities, $floatSet)) > 0) {
            $this->error('fill-gaps is insert-only and skips existing rows. Float/balances need overwrite:');
            $this->line('  php artisan live-sync:push --entity=float --mode=recent --force-all --limit=500 --sync --chunk=25');

            return self::FAILURE;
        }

        foreach ($entities as $e) {
            try {
                $engine->entityConfig($e);
            } catch (\Throwable) {
                $this->error("Unknown entity: {$e}");

                return self::FAILURE;
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
        } else {
            $this->info('Full-table scan (id cursor). Use --since= to narrow.');
        }

        $batchSize = max(50, min(2000, (int) $this->option('batch-size')));
        $chunkSize = $this->option('chunk') !== null
            ? max(1, min(50, (int) $this->option('chunk')))
            : max(1, min(50, (int) config('live_sync.batch.chunk_size', 25)));
        $dryRun = (bool) $this->option('dry-run');
        $untilDone = (bool) $this->option('until-done');

        $this->info('fill-gaps · entities='.count($entities)." · batch-size={$batchSize} · chunk={$chunkSize}".($untilDone ? ' · until-done' : ''));

        $totals = ['pushed' => 0, 'skipped_present' => 0, 'fail' => 0, 'candidates' => 0];

        foreach ($entities as $entity) {
            $cursor = max(0, (int) $this->option('cursor'));
            $this->info("Entity: {$entity} (cursor start > {$cursor})");

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
                );

                $totals['pushed'] += $page['pushed'];
                $totals['skipped_present'] += $page['skipped_present'];
                $totals['fail'] += $page['fail'];
                $totals['candidates'] += $page['candidates'];

                if ($page['fail'] > 0) {
                    $this->error("Stopped {$entity} after batch failure. Resume with --cursor={$cursor}");

                    return self::FAILURE;
                }

                $cursor = $page['next_cursor'];
                $hasMore = $page['has_more'];
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

        $keyMap = [];
        foreach ($rows as $row) {
            $key = $engine->probeKeyLightForModel($entity, $row);
            if ($key !== '') {
                $keyMap[$key] = $row;
            }
        }

        $probe = $client->probeMissing($entity, array_keys($keyMap));
        if (! ($probe['ok'] ?? false)) {
            $this->error('  Probe failed: '.($probe['message'] ?? 'unknown'));

            return [
                'pushed' => 0,
                'skipped_present' => 0,
                'fail' => 1,
                'candidates' => $rows->count(),
                'next_cursor' => $cursor,
                'has_more' => false,
            ];
        }

        $missing = $probe['missing'] ?? [];
        $skipped = count($probe['present'] ?? []);
        $lastId = (int) $rows->last()->getKey();
        $this->line("  id ≤ {$lastId}: candidates {$rows->count()} · present {$skipped} · missing ".count($missing));

        $pushed = 0;
        $fail = 0;

        if ($dryRun) {
            return [
                'pushed' => count($missing),
                'skipped_present' => $skipped,
                'fail' => 0,
                'candidates' => $rows->count(),
                'next_cursor' => $lastId,
                'has_more' => $rows->count() === $batchSize,
            ];
        }

        $missingRows = [];
        foreach ($missing as $key) {
            $row = $keyMap[$key] ?? null;
            if ($row) {
                $missingRows[] = $row;
            }
        }

        foreach (array_chunk($missingRows, $chunkSize) as $chunk) {
            $items = [];
            foreach ($chunk as $row) {
                $items[] = [
                    'entity' => $entity,
                    'operation' => 'upsert',
                    'data' => $engine->serialize($entity, $row),
                ];
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

        return [
            'pushed' => $pushed,
            'skipped_present' => $skipped,
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
