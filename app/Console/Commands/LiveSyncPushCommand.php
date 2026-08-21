<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Payment;
use App\Models\Renter;
use App\Services\LiveSync\LiveSyncOutboundService;
use App\Services\LiveSync\LiveSyncTransmitterClient;
use Carbon\Carbon;
use Illuminate\Console\Command;

class LiveSyncPushCommand extends Command
{
    protected $signature = 'live-sync:push
        {--entity=payment : payment|business|renter|all}
        {--since= : Only rows updated/created on or after this datetime (ISO or Y-m-d)}
        {--id= : Push a single primary key id}
        {--limit=500 : Max rows per entity}
        {--dry-run : Count only, do not send}
        {--sync : Send inline (ignore LIVE_SYNC_QUEUE)}';

    protected $description = 'Push Namecheap (live) rows to Contabo live-sync receiver for catch-up';

    public function handle(LiveSyncOutboundService $outbound, LiveSyncTransmitterClient $client): int
    {
        if (! $client->isConfigured() && ! $this->option('dry-run')) {
            $this->error('Transmitter not configured. On Namecheap set LIVE_SYNC_TRANSMIT_ENABLED=true, LIVE_SYNC_RECEIVER_URL=https://check-outnow.com/api/v1/sync/live, and the same LIVE_SYNC_KEY_ID / LIVE_SYNC_SECRET as Contabo.');

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
        }

        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $forceSync = (bool) $this->option('sync');
        $singleId = $this->option('id') !== null ? (int) $this->option('id') : null;

        if ($forceSync) {
            config(['services.live_sync.queue' => false]);
        }

        $ok = 0;
        $fail = 0;

        foreach ($entities as $e) {
            $this->info("Entity: {$e}");
            $count = 0;

            if ($e === 'payment') {
                $query = Payment::query()->orderBy('id');
                if ($singleId) {
                    $query->where('id', $singleId);
                }
                if ($since) {
                    $query->where(function ($q) use ($since) {
                        $q->where('updated_at', '>=', $since)->orWhere('created_at', '>=', $since);
                    });
                }
                $query->limit($limit)->chunkById(100, function ($rows) use ($outbound, $dryRun, &$ok, &$fail, &$count) {
                    foreach ($rows as $row) {
                        $count++;
                        if ($dryRun) {
                            continue;
                        }
                        $result = $outbound->pushNow('payment', 'upsert', $outbound->paymentPayload($row));
                        if ($result['ok'] ?? false) {
                            $ok++;
                        } else {
                            $fail++;
                            $this->warn("payment #{$row->id}: ".($result['message'] ?? 'failed'));
                        }
                    }
                });
            }

            if ($e === 'business') {
                $query = Business::query()->orderBy('id');
                if ($singleId) {
                    $query->where('id', $singleId);
                }
                if ($since) {
                    $query->where(function ($q) use ($since) {
                        $q->where('updated_at', '>=', $since)->orWhere('created_at', '>=', $since);
                    });
                }
                $query->limit($limit)->chunkById(100, function ($rows) use ($outbound, $dryRun, &$ok, &$fail, &$count) {
                    foreach ($rows as $row) {
                        $count++;
                        if ($dryRun) {
                            continue;
                        }
                        $result = $outbound->pushNow('business', 'upsert', $outbound->businessPayload($row));
                        if ($result['ok'] ?? false) {
                            $ok++;
                        } else {
                            $fail++;
                            $this->warn("business #{$row->id}: ".($result['message'] ?? 'failed'));
                        }
                    }
                });
            }

            if ($e === 'renter') {
                $query = Renter::query()->orderBy('id');
                if ($singleId) {
                    $query->where('id', $singleId);
                }
                if ($since) {
                    $query->where(function ($q) use ($since) {
                        $q->where('updated_at', '>=', $since)->orWhere('created_at', '>=', $since);
                    });
                }
                $query->limit($limit)->chunkById(100, function ($rows) use ($outbound, $dryRun, &$ok, &$fail, &$count) {
                    foreach ($rows as $row) {
                        $count++;
                        if ($dryRun) {
                            continue;
                        }
                        $result = $outbound->pushNow('renter', 'upsert', $outbound->renterPayload($row));
                        if ($result['ok'] ?? false) {
                            $ok++;
                        } else {
                            $fail++;
                            $this->warn("renter #{$row->id}: ".($result['message'] ?? 'failed'));
                        }
                    }
                });
            }

            $this->line(($dryRun ? '[dry-run] Would push' : 'Considered')." {$count} {$e} row(s).");
        }

        if (! $dryRun) {
            $this->info("Done. ok={$ok} fail={$fail}");
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
