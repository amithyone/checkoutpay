<?php

namespace App\Http\Controllers\Api\Rentals\Concerns;

use App\Models\Rental;
use App\Services\Rentals\RentalEscrowService;

trait FormatsRentalDetail
{
    protected function rentalDetailPayload(Rental $rental): array
    {
        $rental->loadMissing(['business', 'items', 'escrow', 'disputes']);

        $data = $rental->toArray();
        /** @var RentalEscrowService $escrowService */
        $escrowService = app(RentalEscrowService::class);

        $data['escrow'] = $rental->escrow
            ? $rental->escrow->toApiArray()
            : [
                'status' => 'held',
                'rent_held' => (float) $rental->total_amount,
                'deposit_held' => (float) $rental->deposit_amount,
                'rent_released_at' => null,
                'deposit_released_at' => null,
            ];

        $data['cancellable'] = $escrowService->isCancellable($rental);
        $data['cancel_deadline'] = $escrowService->cancelDeadline($rental)?->toIso8601String();

        return $data;
    }
}
