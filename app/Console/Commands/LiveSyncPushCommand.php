<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Payment;
use App\Models\Renter;
use App\Models\Setting;
use App\Services\LiveSync\LiveSyncOutboundService;
use App\Services\LiveSync\LiveSyncTransmitterClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class LiveSyncPushCommand extends Command
{
    public const WATERMARK_KEY = 'live_sync_push_watermark';

    protected $signature = 'live-sync:push
        {--entity=payment : payment|business|renter|all}
        {--mode=missing : missing=only keys Contabo lacks; recent=upsert rows changed since watermark}
        {--since= : Override watermark / default lookback (ISO or Y-m-d)}
        {--id= : Push a single primary key id}
        {--limit=200 : Max candidate rows per entity (never a full-table dump)}
        {--force-all : Allow scan without a since/watermark window (still capped by --limit)}
        {--dry-run : Count / probe only, do not send upserts}
        {--sync : Send inline (ignore LIVE_SYNC_QUEUE)}';

    protected $description = 'Push only missing (or recently changed) Namecheap rows to Contabo — not the whole DB';

    public function handle(LiveSyncOutboundService $outbound, LiveSyncTransmitterClient $client): int
    {
        if (! $client->isConfigured() && ! $this->option('dry-run')) {
            $this->error('Transmitter not configured. On Namecheap set LIVE_SYNC_TRANSMIT_ENABLED=true, LIVE_SYNC_RECEIVER_URL=https://check-outnow.com/api/v1/sync/live, and the same LIVE_SYNC_KEY_ID / LIVE_SYNC_SECRET as Contabo.');

            return self::FAILURE;
        }

        $mode = strtolower(trim((string) $this->option('mode')));
        if (! in_array($mode, ['missing', 'recent'], true)) {
            $this->error('--mode must be missing or recent');

            return self::FAILURE;
        }

        $entity = strtolower(trim((string) $this->option('entity')));
        $entities = $entity === 'all' ? ['payment', 'business', 'renter'] : [$entity];
        foreach ($entities as $e) {
            if (! in_array($e, ['payment', 'business', 'renter'], true)) {
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
        } else {
            $since = $this->resolveSinceDefault();
        }

        if ($since === null && ! $this->option('id') && ! $this->option('force-all')) {
            $this->error('Refusing full-table sync. Use --since=, rely on watermark, or pass --force-all (still limited).');

            return self::FAILURE;
        }

        if ($since !== null) {
            $this->info('Window since: '.$since->toDateTimeString().' UTC');
        }
        $this->info('Mode: '.$mode.' · limit: '.(int) $this->option('limit'));

        $limit = max(1, min(2000, (int) $this->option('limit')));
        $dryRun = (bool) $this->option('dry-run');
        if ((bool) $this->option('sync')) {
            config(['services.live_sync.queue' => false]);
        }
        $singleId = $this->option('id') !== null ? (int) $this->option('id') : null;

        $ok = 0;
        $fail = 0;
        $skippedPresent = 0;
        $candidates = 0;

        foreach ($entities as $e) {
            $this->info("Entity: {$e}");
            $query = $this->baseQuery($e, $since, $singleId)->limit($limit);
            $rows = $query->get();
            $candidates += $rows->count();

            if ($rows->isEmpty()) {
                $this->line('No candidates in window.');

                continue;
            }

            if ($mode === 'missing' && ! $singleId) {
                $keyMap = [];
                foreach ($rows as $row) {
                    $key = $this->rowKey($e, $row);
                    if ($key !== '') {
                        $keyMap[$key] = $row;
                    }
                }
                $keys = array_keys($keyMap);
                $probe = $client->probeMissing($e, $keys);
                if (! ($probe['ok'] ?? false)) {
                    $this->error('Probe failed: '.($probe['message'] ?? 'unknown').' — aborting this entity (no mass push).');
                    $fail++;

                    continue;
                }

                $missing = $probe['missing'] ?? [];
                $skippedPresent += count($probe['present'] ?? []);
                $this->line('Candidates '.$rows->count().' · already on Contabo '.count($probe['present'] ?? []).' · missing '.count($missing));

                foreach ($missing as $key) {
                    $row = $keyMap[$key] ?? null;
                    if (! $row) {
                        continue;
                    }
                    if ($dryRun) {
                        $ok++;

                        continue;
                    }
                    $result = $this->pushRow($outbound, $e, $row);
                    if ($result['ok'] ?? false) {
                        $ok++;
                    } else {
                        $fail++;
                        $this->warn("{$e} {$key}: ".($result['message'] ?? 'failed'));
                    }
                }
            } else {
                $this->line('Candidates '.$rows->count().' (recent upsert mode)');
                foreach ($rows as $row) {
                    if ($dryRun) {
                        $ok++;

                        continue;
                    }
                    $result = $this->pushRow($outbound, $e, $row);
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

    private function resolveSinceDefault(): ?Carbon
    {
        if ($this->option('id') || $this->option('force-all')) {
            return null;
        }

        $raw = Setting::get(self::WATERMARK_KEY);
        if (is_string($raw) && trim($raw) !== '') {
            try {
                // Overlap 1 hour so we do not miss edge updates
                return Carbon::parse($raw)->subHour();
            } catch (\Throwable) {
                // fall through
            }
        }

        // Safe default: last 48 hours only
        return now()->subHours(48);
    }

    private function baseQuery(string $entity, ?Carbon $since, ?int $singleId): Builder
    {
        $query = match ($entity) {
            'payment' => Payment::query()->orderBy('id'),
            'business' => Business::query()->orderBy('id'),
            'renter' => Renter::query()->orderBy('id'),
            default => Payment::query()->orderBy('id'),
        };

        if ($singleId) {
            return $query->where('id', $singleId);
        }

        if ($since) {
            $query->where(function ($q) use ($since) {
                $q->where('updated_at', '>=', $since)->orWhere('created_at', '>=', $since);
            });
        }

        return $query;
    }

    private function rowKey(string $entity, Payment|Business|Renter $row): string
    {
        return match ($entity) {
            'payment' => trim((string) $row->transaction_id),
            'business' => trim((string) ($row->business_id ?: $row->email)),
            'renter' => strtolower(trim((string) $row->email)),
            default => '',
        };
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    private function pushRow(LiveSyncOutboundService $outbound, string $entity, Payment|Business|Renter $row): array
    {
        return match ($entity) {
            'payment' => $outbound->pushNow('payment', 'upsert', $outbound->paymentPayload($row)),
            'business' => $outbound->pushNow('business', 'upsert', $outbound->businessPayload($row)),
            'renter' => $outbound->pushNow('renter', 'upsert', $outbound->renterPayload($row)),
            default => ['ok' => false, 'message' => 'Unknown entity'],
        };
    }
}
