<?php

namespace App\Observers;

use App\Services\LiveSync\LiveSyncGenericEngine;
use App\Services\LiveSync\LiveSyncOutboundService;
use Illuminate\Database\Eloquent\Model;

class LiveSyncOutboundObserver
{
    /** @var array<class-string, string> */
    private array $modelToEntity = [];

    public function __construct(
        private LiveSyncOutboundService $outbound,
        private LiveSyncGenericEngine $engine,
    ) {
        foreach ($this->engine->observableEntities() as $entity) {
            $this->modelToEntity[$this->engine->modelClass($entity)] = $entity;
        }
    }

    public function saved(Model $model): void
    {
        $this->push($model, 'upsert');
    }

    public function deleted(Model $model): void
    {
        $this->push($model, 'delete');
    }

    private function push(Model $model, string $operation): void
    {
        if (! $this->outbound->shouldTransmit()) {
            return;
        }

        $entity = $this->modelToEntity[$model::class] ?? null;
        if ($entity === null) {
            return;
        }

        $this->outbound->pushEntity($entity, $model, $operation);
    }
}
