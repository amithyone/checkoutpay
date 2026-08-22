<?php

namespace App\Services\LiveSync;

use App\Models\LiveSyncOutboundCursor;
use Illuminate\Database\Eloquent\Model;

final class LiveSyncCursorService
{
    public function cursorFor(string $entity): LiveSyncOutboundCursor
    {
        return LiveSyncOutboundCursor::query()->firstOrCreate(
            ['entity' => $entity],
            [
                'last_origin_id' => 0,
                'status' => LiveSyncOutboundCursor::STATUS_BACKFILL,
                'rows_pushed_total' => 0,
            ],
        );
    }

    public function lastOriginId(string $entity): int
    {
        return (int) $this->cursorFor($entity)->last_origin_id;
    }

    public function isCaughtUp(string $entity): bool
    {
        return $this->cursorFor($entity)->isCaughtUp();
    }

    public function advance(string $entity, int $lastOriginId, int $pushedCount = 0): void
    {
        if ($lastOriginId < 0) {
            return;
        }

        $cursor = $this->cursorFor($entity);
        $updates = [
            'last_run_at' => now(),
        ];

        if ($lastOriginId > (int) $cursor->last_origin_id) {
            $updates['last_origin_id'] = $lastOriginId;
        }

        if ($pushedCount > 0) {
            $updates['rows_pushed_total'] = (int) $cursor->rows_pushed_total + $pushedCount;
        }

        $cursor->update($updates);
    }

    public function advanceIfHigher(string $entity, int $originId): void
    {
        if ($originId <= 0) {
            return;
        }

        $cursor = $this->cursorFor($entity);
        if ($originId > (int) $cursor->last_origin_id) {
            $cursor->update([
                'last_origin_id' => $originId,
                'last_run_at' => now(),
            ]);
        }
    }

    public function markCaughtUp(string $entity, ?int $maxOriginId = null): void
    {
        $cursor = $this->cursorFor($entity);
        $updates = [
            'status' => LiveSyncOutboundCursor::STATUS_CAUGHT_UP,
            'last_run_at' => now(),
        ];

        if ($maxOriginId !== null && $maxOriginId >= 0) {
            $updates['max_origin_id'] = $maxOriginId;
            if ($maxOriginId > (int) $cursor->last_origin_id) {
                $updates['last_origin_id'] = $maxOriginId;
            }
        }

        $cursor->update($updates);
    }

    public function reset(string $entity): void
    {
        LiveSyncOutboundCursor::query()->updateOrCreate(
            ['entity' => $entity],
            [
                'last_origin_id' => 0,
                'max_origin_id' => null,
                'status' => LiveSyncOutboundCursor::STATUS_BACKFILL,
                'last_run_at' => null,
                'rows_pushed_total' => 0,
            ],
        );
    }

    public function maxOriginIdForEntity(string $entity, LiveSyncGenericEngine $engine): int
    {
        try {
            /** @var class-string<Model> $class */
            $class = $engine->modelClass($entity);
            $max = $class::query()->max('id');

            return is_numeric($max) ? (int) $max : 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
