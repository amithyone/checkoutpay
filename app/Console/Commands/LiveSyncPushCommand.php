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
        {--limit=200 : Max candidate rows per entity}
        {--force-all : Allow scan without a since/watermark window (still capped by --limit)}
        {--dry-run : Count / probe only, do not send upserts}
        {--sync : Send inline (ignore LIVE_SYNC_QUEUE)}
        {--delay-ms=0 : Pause between upserts (helps avoid receiver rate limits)}';

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

        // Bank float rows already exist on Contabo from old dumps; missing-only never refreshes balances.
        $floatSet = $engine->floatEntities();
        $pushingFloat = $entityOpt === 'float'
            || count(array_intersect($entities, $floatSet)) === count($entities);
        if ($entityOpt === 'float' && $mode === 'missing') {
            $this->warn('float entities require balance upserts — switching to --mode=recent');
            $mode = 'recent';
        } elseif ($mode === 'missing' && array_intersect($entities, $floatSet) !== []) {
            $this->warn('Tip: --mode=missing skips rows Contabo already has. For site float ≈ live, use:');
            $this->warn('  php artisan live-sync:push --entity=float --mode=recent --force-all --limit=500 --sync');
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

        if ($since === null && ! $this->option('id') && ! $this->option('force-all')) {
            $this->error('Refusing full-table sync. Use --since=, watermark, or --force-all.');

            return self::FAILURE;
        }

        if ($since !== null) {
            $this->info('Window since: '.$since->toDateTimeString().' UTC');
        }
        $this->info('Mode: '.$mode.' · entities: '.count($entities).' · limit: '.(int) $this->option('limit'));
        if ($pushingFloat && $mode === 'recent') {
            $this->info('Float push: upserting renter/business/whatsapp_wallet balances onto Contabo.');
        }
        $limit = max(1, min(2000, (int) $this->option('limit')));
        $dryRun = (bool) $this->option('dry-run');
        if ((bool) $this->option('sync')) {
            config(['services.live_sync.queue' => false]);
        }
        $singleId = $this->option('id') !== null ? (int) $this->option('id') : null;
        $delayMs = max(0, min(5000, (int) $this->option('delay-ms')));

        $ok = 0;
        $fail = 0;
        $skippedPresent = 0;
        $candidates = 0;

        foreach ($entities as $e) {
            $this->info("Entity: {$e}");
            /** @var class-string<Model> $class */
            $class = $engine->modelClass($e);
            $query = $class::query()->orderBy('id');
            if ($singleId) {
                $query->where('id', $singleId);
            } elseif ($since) {
                $query->where(function ($q) use ($since) {
                    $q->where('updated_at', '>=', $since)->orWhere('created_at', '>=', $since);
                });
            }
            $rows = $query->limit($limit)->get();
            $candidates += $rows->count();

            if ($rows->isEmpty()) {
                $this->line('No candidates.');

                continue;
            }

            // Eager extras for serialize where cheap
            $rows->loadMissing($this->eagerRelations($e));

            if ($mode === 'missing' && ! $singleId) {
                $keyMap = [];
                foreach ($rows as $row) {
                    $key = $engine->probeKeyForModel($e, $row);
                    if ($key !== '') {
                        $keyMap[$key] = $row;
                    }
                }
                $probe = $client->probeMissing($e, array_keys($keyMap));
                if (! ($probe['ok'] ?? false)) {
                    $this->error('Probe failed: '.($probe['message'] ?? 'unknown').' — aborting this entity.');
                    $fail++;

                    continue;
                }
                $missing = $probe['missing'] ?? [];
                $skippedPresent += count($probe['present'] ?? []);
                $this->line('Candidates '.$rows->count().' · present '.count($probe['present'] ?? []).' · missing '.count($missing));

                foreach ($missing as $key) {
                    $row = $keyMap[$key] ?? null;
                    if (! $row) {
                        continue;
                    }
                    if ($dryRun) {
                        $ok++;

                        continue;
                    }
                    $result = $this->pushRow($outbound, $engine, $e, $row, $delayMs);
                    if ($result['ok'] ?? false) {
                        $ok++;
                    } else {
                        $fail++;
                        $this->warn("{$e} {$key}: ".($result['message'] ?? 'failed'));
                    }
                }
            } else {
                $this->line('Candidates '.$rows->count().' (recent upsert)');
                foreach ($rows as $row) {
                    if ($dryRun) {
                        $ok++;

                        continue;
                    }
                    $result = $this->pushRow($outbound, $engine, $e, $row, $delayMs);
                    if ($result['ok'] ?? false) {
                        $ok++;
                    } else {
                        $fail++;
                        $this->warn("{$e} #{$row->id}: ".($result['message'] ?? 'failed'));
                    }
                }
            }
        }

        if (! $dryRun && $fail === 0 && $singleId === null) {
            Setting::set(self::WATERMARK_KEY, now()->toIso8601String(), 'string', 'live_sync', 'Last successful live-sync:push watermark');
            $this->info('Watermark updated.');
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."candidates={$candidates} pushed_or_would={$ok} skipped_present={$skippedPresent} fail={$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
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
        if ($this->option('id') || $this->option('force-all')) {
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
