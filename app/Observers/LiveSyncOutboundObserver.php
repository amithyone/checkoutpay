<?php

namespace App\Observers;

use App\Models\Business;
use App\Models\Payment;
use App\Models\Renter;
use App\Services\LiveSync\LiveSyncOutboundService;

class LiveSyncOutboundObserver
{
    public function __construct(
        private LiveSyncOutboundService $outbound,
    ) {}

    public function saved(Payment|Business|Renter $model): void
    {
        if (! $this->outbound->shouldTransmit()) {
            return;
        }

        match (true) {
            $model instanceof Payment => $this->outbound->pushPayment($model, 'upsert'),
            $model instanceof Business => $this->outbound->pushBusiness($model, 'upsert'),
            $model instanceof Renter => $this->outbound->pushRenter($model, 'upsert'),
            default => null,
        };
    }

    public function deleted(Payment|Business|Renter $model): void
    {
        if (! $this->outbound->shouldTransmit()) {
            return;
        }

        match (true) {
            $model instanceof Payment => $this->outbound->pushPayment($model, 'delete'),
            $model instanceof Business => $this->outbound->pushBusiness($model, 'delete'),
            $model instanceof Renter => $this->outbound->pushRenter($model, 'delete'),
            default => null,
        };
    }
}
