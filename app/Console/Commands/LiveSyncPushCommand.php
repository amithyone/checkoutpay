<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\LiveSync\LiveSyncGenericEngine;
use App\Services\LiveSync\LiveSyncOutboundService;
use App\Services\LiveSync\LiveSyncTransmitterClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class LiveSyncPushCommand extends Command
{
    public const WATERMARK_KEY = 'live_sync_push_watermark';

    protected $signature = 'live-sync:push
        {--entity=common : Entity name, common, float (balances), or all}
        {--mode=missing : missing=only keys Contabo lacks; recent=upsert balances/rows}
        {--since= : Override watermark / default lookback (ISO or Y-m-d)}
        {--id= : Push a single primary key id}
        {--limit=200 : Max candidate rows per entity (per page with --until-done)}
        {--cursor=0 : Start after this id (paginate large backlogs)}
        {--force-all : Allow scan without a since/watermark window (still capped by --limit per page)}
        {--until-done : Keep paging by id until no more candidates}
        {--chunk= : Events per HTTP batch in missing mode (default 25, max 50; 1=row-by-row)}
        {--dry-run : Count / probe only, do not send upserts}
        {--sync : Send inline (ignore LIVE_SYNC_QUEUE)}
        {--delay-ms=0 : Pause between upserts when --chunk=1 (helps avoid receiver rate limits)}';

    protected $description = 'Push common money-path data Namecheap → Contabo (use --entity=float --mode=recent for bank float)';

    public function handle(
        LiveSyncOutboundService $outbound,
        LiveSyncTransmitterClient $client,
        LiveSyncGenericEngine $engine,
    ): int {
        if (! $client->isConfigured() && ! $this->option('dry-run')) {
            $this->error('Transmitter not configured on this host.');

            return self::FAILURE;
        }

        $mode = strtolower(trim((string) $this->option('mode')));
        if (! in_array($mode, ['missing', 'recent'], true)) {
            $this->error('--mode must be missing or recent');

            return self::FAILURE;
        }

        $entityOpt = strtolower(trim((string) $this->option('entity')));
        $entities = match ($entityOpt) {
            'common', 'all' => $engine->commonEntities(),
            'float' => $engine->floatEntities(),
            default => [$entityOpt],
        };

        foreach ($entities as $e) {
            try {
                $engine->entityConfig($e);
            } catch (\Throwable) {
                $this->error("Unknown entity: {$e}");

                return self::FAILURE;
            }
        }

        $floatSet = $engine->floatEntities();
        $pushingFloat = $entityOpt === 'float'
            || count(array_intersect($entities, $floatSet)) === count($entities);
        if ($entityOpt === 'float' && $mode === 'missing') {
            $this->warn('float entities require balance upserts — switching to --mode=recent');
            $mode = 'recent';
        } elseif ($mode === 'missing' && array_intersect($entities, $floatSet) !== []) {
            $this->warn('Tip: --mode=missing skips rows Contabo already has. For site float ≈ live, use:');
            $this->warn('  php artisan live-sync:push --entity=float --mode=recent --force-all --limit=500 --sync --chunk=25');
        }

        $since = null;
        if ($this->option('since')) {
            try {
                $since = Carbon::parse((string) $this->option('since'));
            } catch (\Throwable) {
                $this->error('Invalid --since datetime.');

                return self::FAILURE;
            }
        } else {
            $since = $this->resolveSinceDefault();
        }

        if ($since === null && ! $this->option('id') && ! $this->option('force-all') && ! $this->option('until-done')) {
            $this->error('Refusing full-table sync. Use --since=, watermark, --force-all, or --until-done.');

            return self::FAILURE;
        }

        if ($since !== null) {
            $this->info('Window since: '.$since->toDateTimeString().' UTC');
        }

        $limit = max(1, min(2000, (int) $this->option('limit')));
        $chunkSize = $this->option('chunk') !== null
            ? max(1, min(50, (int) $this->option('chunk')))
            : max(1, min(50, (int) config('live_sync.batch.chunk_size', 25)));
        $dryRun = (bool) $this->option('dry-run');
        if ((bool) $this->option('sync')) {
            config(['services.live_sync.queue' => false]);
        }
        $singleId = $this->option('id') !== null ? (int) $this->option('id') : null;
        $delayMs = max(0, min(5000, (int) $this->option('delay-ms')));
        $untilDone = (bool) $this->option('until-done');
        $startCursor = max(0, (int) $this->option('cursor'));

        $this->info('Mode: '.$mode.' · entities: '.count($entities)." · limit: {$limit} · chunk: {$chunkSize}".($untilDone ? ' · until-done' : ''));
        if ($pushingFloat && $mode === 'recent') {
            $this->info('Float push: upserting renter/business/whatsapp_wallet balances onto Contabo.');
        }
        if ($mode === 'missing' && ! $singleId) {
            $this->info('For full manual gap-fill (insert-only, faster): php artisan live-sync:fill-gaps --entity=common --until-done --sync');
        }

        $ok = 0;
        $fail = 0;
        $skippedPresent = 0;
        $candidates = 0;

        foreach ($entities as $e) {
            $this->info("Entity: {$e}");
            $cursor = $startCursor;

            do {
                $page = $this->pushEntityPage(
                    $outbound,
                    $client,
                    $engine,
                    $e,
                    $mode,
                    $since,
                    $singleId,
                    $cursor,
                    $limit,
                    $chunkSize,
                    $dryRun,
                    $delayMs,
                );

                $ok += $page['ok'];
                $fail += $page['fail'];
                $skippedPresent += $page['skipped_present'];
                $candidates += $page['candidates'];
                $cursor = $page['next_cursor'];
                $hasMore = $page['has_more'];

                if ($page['fail'] > 0 && $untilDone) {
                    $this->error("Stopped after failure. Resume with --cursor={$page['next_cursor']}");

                    break;
                }
            } while ($untilDone && $hasMore && $singleId === null);
        }

        if (! $dryRun && $fail === 0 && $singleId === null) {
            Setting::set(self::WATERMARK_KEY, now()->toIso8601String(), 'string', 'live_sync', 'Last successful live-sync:push watermark');
            $this->info('Watermark updated.');
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."candidates={$candidates} pushed_or_would={$ok} skipped_present={$skippedPresent} fail={$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{ok: int, fail: int, skipped_present: int, candidates: int, next_cursor: int, has_more: bool}
     */
    private function pushEntityPage(
        LiveSyncOutboundService $outbound,
        LiveSyncTransmitterClient $client,
        LiveSyncGenericEngine $engine,
        string $entity,
        string $mode,
        ?Carbon $since,
        ?int $singleId,
        int $cursor,
        int $limit,
        int $chunkSize,
        bool $dryRun,
        int $delayMs,
    ): array {
        /** @var class-string<Model> $class */
        $class = $engine->modelClass($entity);
        $query = $class::query()->orderBy('id');
        if ($singleId) {
            $query->where('id', $singleId);
        } else {
            if ($cursor > 0) {
                $query->where('id', '>', $cursor);
            }
            if ($since) {
                $query->where(function ($q) use ($since) {
                    $q->where('updated_at', '>=', $since)->orWhere('created_at', '>=', $since);
                });
            }
        }

        $rows = $query->limit($limit)->get();
        if ($rows->isEmpty()) {
            $this->line('No candidates.');

            return [
                'ok' => 0,
                'fail' => 0,
                'skipped_present' => 0,
                'candidates' => 0,
                'next_cursor' => $cursor,
                'has_more' => false,
            ];
        }

        $rows->loadMissing($this->eagerRelations($entity));
        $lastId = (int) $rows->last()->getKey();

        if ($mode === 'missing' && ! $singleId) {
            $keyMap = [];
            foreach ($rows as $row) {
                $key = $engine->probeKeyLightForModel($entity, $row);
                if ($key !== '') {
                    $keyMap[$key] = $row;
                }
            }
            $probe = $client->probeMissing($entity, array_keys($keyMap));
            if (! ($probe['ok'] ?? false)) {
                $this->error('Probe failed: '.($probe['message'] ?? 'unknown').' — aborting this entity.');

                return [
                    'ok' => 0,
                    'fail' => 1,
                    'skipped_present' => 0,
                    'candidates' => $rows->count(),
                    'next_cursor' => $cursor,
                    'has_more' => false,
                ];
            }
            $missing = $probe['missing'] ?? [];
            $skippedPresent = count($probe['present'] ?? []);
            $this->line('Candidates '.$rows->count().' · present '.$skippedPresent.' · missing '.count($missing));

            $ok = 0;
            $fail = 0;
            $missingRows = [];
            foreach ($missing as $key) {
                $row = $keyMap[$key] ?? null;
                if ($row) {
                    $missingRows[] = $row;
                }
            }

            if ($dryRun) {
                return [
                    'ok' => count($missingRows),
                    'fail' => 0,
                    'skipped_present' => $skippedPresent,
                    'candidates' => $rows->count(),
                    'next_cursor' => $lastId,
                    'has_more' => $rows->count() === $limit,
                ];
            }

            if ($chunkSize > 1) {
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
                        $ok += (int) ($result['processed'] ?? count($chunk));
                        $fail += (int) ($result['failed'] ?? 0);
                    } else {
                        $fail += count($chunk);
                        $this->warn('Batch failed: '.($result['message'] ?? 'unknown'));
                    }
                }
            } else {
                foreach ($missingRows as $row) {
                    $result = $this->pushRow($outbound, $engine, $entity, $row, $delayMs);
                    if ($result['ok'] ?? false) {
                        $ok++;
                    } else {
                        $fail++;
                        $this->warn("{$entity} #{$row->id}: ".($result['message'] ?? 'failed'));
                    }
                }
            }

            return [
                'ok' => $ok,
                'fail' => $fail,
                'skipped_present' => $skippedPresent,
                'candidates' => $rows->count(),
                'next_cursor' => $lastId,
                'has_more' => $rows->count() === $limit,
            ];
        }

        $this->line('Candidates '.$rows->count().' (recent upsert)');
        $ok = 0;
        $fail = 0;

        if ($dryRun) {
            return [
                'ok' => $rows->count(),
                'fail' => 0,
                'skipped_present' => 0,
                'candidates' => $rows->count(),
                'next_cursor' => $lastId,
                'has_more' => $rows->count() === $limit,
            ];
        }

        if ($chunkSize > 1) {
            foreach (array_chunk($rows->all(), $chunkSize) as $chunk) {
                $items = [];
                foreach ($chunk as $row) {
                    $items[] = [
                        'entity' => $entity,
                        'operation' => 'upsert',
                        'data' => $engine->serialize($entity, $row),
                    ];
                }
                $result = $outbound->pushBatchNow($items);
                if ($result['ok'] ?? false) {
                    $ok += (int) ($result['processed'] ?? count($chunk));
                    $fail += (int) ($result['failed'] ?? 0);
                } else {
                    $fail += count($chunk);
                    $this->warn('Batch failed: '.($result['message'] ?? 'unknown'));
                }
            }
        } else {
            foreach ($rows as $row) {
                $result = $this->pushRow($outbound, $engine, $entity, $row, $delayMs);
                if ($result['ok'] ?? false) {
                    $ok++;
                } else {
                    $fail++;
                    $this->warn("{$entity} #{$row->id}: ".($result['message'] ?? 'failed'));
                }
            }
        }

        return [
            'ok' => $ok,
            'fail' => $fail,
            'skipped_present' => 0,
            'candidates' => $rows->count(),
            'next_cursor' => $lastId,
            'has_more' => $rows->count() === $limit,
        ];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    private function pushRow(
        LiveSyncOutboundService $outbound,
        LiveSyncGenericEngine $engine,
        string $entity,
        Model $row,
        int $delayMs,
    ): array {
        $result = $outbound->pushNow($entity, 'upsert', $engine->serialize($entity, $row));
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        return $result;
    }

    private function resolveSinceDefault(): ?Carbon
    {
        if ($this->option('id') || $this->option('force-all') || $this->option('until-done')) {
            return null;
        }

        $raw = Setting::get(self::WATERMARK_KEY);
        if (is_string($raw) && trim($raw) !== '') {
            try {
                return Carbon::parse($raw)->subHour();
            } catch (\Throwable) {
            }
        }

        return now()->subHours(48);
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
