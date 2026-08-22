<?php

namespace App\Console\Commands;

use App\Services\LiveSync\LiveSyncCursorService;
use App\Services\LiveSync\LiveSyncGenericEngine;
use Illuminate\Console\Command;

class LiveSyncStatusCommand extends Command
{
    protected $signature = 'live-sync:status
        {--entity= : Single entity (default: all gap-fill entities)}';

    protected $description = 'Show per-entity outbound sync cursor vs table max id';

    public function handle(LiveSyncCursorService $cursors, LiveSyncGenericEngine $engine): int
    {
        $entityOpt = $this->option('entity');
        $floatSet = $engine->floatEntities();

        if ($entityOpt !== null && trim((string) $entityOpt) !== '') {
            $entities = [strtolower(trim((string) $entityOpt))];
        } else {
            $entities = array_values(array_diff($engine->commonEntities(), $floatSet));
        }

        $rows = [];
        foreach ($entities as $entity) {
            try {
                $engine->entityConfig($entity);
            } catch (\Throwable) {
                $this->warn("Unknown entity: {$entity}");

                continue;
            }

            $cursor = $cursors->cursorFor($entity);
            $maxId = $cursors->maxOriginIdForEntity($entity, $engine);
            $pending = max(0, $maxId - (int) $cursor->last_origin_id);

            $rows[] = [
                $entity,
                (string) $cursor->last_origin_id,
                (string) $maxId,
                (string) $pending,
                $cursor->status,
                $cursor->last_run_at?->toDateTimeString() ?? '—',
                (string) $cursor->rows_pushed_total,
            ];
        }

        if ($rows === []) {
            $this->info('No entities to show.');

            return self::SUCCESS;
        }

        $this->table(
            ['entity', 'cursor', 'max_id', 'pending', 'status', 'last_run', 'pushed_total'],
            $rows,
        );

        return self::SUCCESS;
    }
}
