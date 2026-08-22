<?php

namespace App\Console\Commands;

use App\Services\LiveSync\LiveSyncGenericEngine;
use Illuminate\Console\Command;

/**
 * Incremental catch-up: push only rows with id > saved cursor (no full re-scan).
 */
class LiveSyncIncrementalCommand extends Command
{
    protected $signature = 'live-sync:incremental
        {--entity=common : Entity name, common, or a single gap-fill entity}
        {--batch-size=500 : Rows per page}
        {--chunk= : Events per HTTP batch}
        {--sync : Send inline (required on Namecheap)}';

    protected $description = 'Push new rows since last saved cursor (for cron / quick manual runs)';

    public function handle(LiveSyncGenericEngine $engine): int
    {
        if (! (bool) config('services.live_sync.transmit_enabled', false)) {
            $this->error('Transmitter not configured. Run on Namecheap with LIVE_SYNC_TRANSMIT_ENABLED=true.');

            return self::FAILURE;
        }

        $args = [
            '--entity' => (string) $this->option('entity'),
            '--batch-size' => (string) $this->option('batch-size'),
            '--until-done' => true,
            '--sync' => (bool) $this->option('sync'),
        ];

        if ($this->option('chunk') !== null) {
            $args['--chunk'] = (string) $this->option('chunk');
        }

        $this->info('Running incremental gap-fill from saved cursors…');

        return $this->call('live-sync:fill-gaps', $args);
    }
}
