<?php

namespace App\Services\Rentals;

use App\Models\Business;
use App\Models\Rental;
use App\Models\RentalDispute;
use App\Models\RentalEscrow;
use App\Models\Renter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RentalEscrowService
{
    public function holdForRental(Rental $rental): RentalEscrow
    {
        $rentHeld = (float) $rental->total_amount;
        $depositHeld = (float) $rental->deposit_amount;

        return RentalEscrow::query()->updateOrCreate(
            ['rental_id' => $rental->id],
            [
                'status' => RentalEscrow::STATUS_HELD,
                'rent_held' => $rentHeld,
                'deposit_held' => $depositHeld,
                'rent_released' => 0,
                'deposit_released' => 0,
                'rent_released_at' => null,
                'deposit_released_at' => null,
            ]
        );
    }

    public function freezeForDispute(Rental $rental): void
    {
        $escrow = $this->escrowFor($rental);
        if ($escrow) {
            $escrow->update(['status' => RentalEscrow::STATUS_FROZEN]);
        }
    }

    public function releaseRentToVendor(Rental $rental): void
    {
        DB::transaction(function () use ($rental) {
            $escrow = RentalEscrow::query()
                ->where('rental_id', $rental->id)
                ->lockForUpdate()
                ->first();

            if (! $escrow || (float) $escrow->rent_held <= 0) {
                return;
            }

            if ($escrow->rent_released_at) {
                return;
            }

            $amount = (float) $escrow->rent_held;
            $business = Business::query()->lockForUpdate()->find($rental->business_id);
            if ($business) {
                $business->increment('balance', $amount);
            }

            $escrow->update([
                'rent_released' => $amount,
                'rent_released_at' => now(),
                'status' => $escrow->deposit_released_at
                    ? RentalEscrow::STATUS_RELEASED
                    : RentalEscrow::STATUS_PARTIALLY_RELEASED,
            ]);
        });
    }

    public function releaseDepositToRenter(Rental $rental, ?float $captureAmount = null): void
    {
        DB::transaction(function () use ($rental, $captureAmount) {
            $escrow = RentalEscrow::query()
                ->where('rental_id', $rental->id)
                ->lockForUpdate()
                ->first();

            if (! $escrow || (float) $escrow->deposit_held <= 0) {
                return;
            }

            if ($escrow->deposit_released_at) {
                return;
            }

            $held = (float) $escrow->deposit_held;
            $capture = max(0, min($held, (float) ($captureAmount ?? 0)));
            $refund = round($held - $capture, 2);

            if ($refund > 0 && $rental->renter_id) {
                $renter = Renter::query()->lockForUpdate()->find($rental->renter_id);
                if ($renter) {
                    $renter->wallet_balance = (float) ($renter->wallet_balance ?? 0) + $refund;
                    $renter->save();
                }
            }

            if ($capture > 0) {
                $business = Business::query()->lockForUpdate()->find($rental->business_id);
                if ($business) {
                    $business->increment('balance', $capture);
                }
            }

            $escrow->update([
                'deposit_released' => $refund,
                'deposit_released_at' => now(),
                'status' => $escrow->rent_released_at
                    ? RentalEscrow::STATUS_RELEASED
                    : RentalEscrow::STATUS_PARTIALLY_RELEASED,
            ]);
        });
    }

    public function refundAll(Rental $rental, string $reason = ''): void
    {
        DB::transaction(function () use ($rental) {
            $escrow = RentalEscrow::query()
                ->where('rental_id', $rental->id)
                ->lockForUpdate()
                ->first();

            $rentRefund = (float) ($escrow?->rent_held ?? $rental->total_amount);
            $depositRefund = (float) ($escrow?->deposit_held ?? $rental->deposit_amount);
            $total = round($rentRefund + $depositRefund, 2);

            if ($total > 0 && $rental->renter_id && ! $rental->is_walk_in) {
                $renter = Renter::query()->lockForUpdate()->find($rental->renter_id);
                if ($renter) {
                    $renter->wallet_balance = (float) ($renter->wallet_balance ?? 0) + $total;
                    $renter->save();
                }
            }

            if ($escrow) {
                $escrow->update([
                    'status' => RentalEscrow::STATUS_REFUNDED,
                    'rent_held' => 0,
                    'deposit_held' => 0,
                    'rent_released' => 0,
                    'deposit_released' => 0,
                    'rent_released_at' => $escrow->rent_released_at ?? now(),
                    'deposit_released_at' => $escrow->deposit_released_at ?? now(),
                ]);
            } else {
                RentalEscrow::query()->create([
                    'rental_id' => $rental->id,
                    'status' => RentalEscrow::STATUS_REFUNDED,
                    'rent_held' => 0,
                    'deposit_held' => 0,
                ]);
            }
        });

        Log::info('Rental escrow refunded', ['rental_id' => $rental->id, 'reason' => $reason]);
    }

    public function resolveDispute(RentalDispute $dispute, string $resolution, float $captureAmount = 0, ?string $notes = null): void
    {
        $rental = $dispute->rental;
        if (! $rental) {
            return;
        }

        DB::transaction(function () use ($dispute, $rental, $resolution, $captureAmount, $notes) {
            match ($resolution) {
                'release_deposit' => $this->releaseDepositToRenter($rental, 0),
                'capture_partial' => $this->releaseDepositToRenter($rental, $captureAmount),
                'capture_full' => $this->releaseDepositToRenter($rental, (float) $rental->deposit_amount),
                default => null,
            };

            $escrow = RentalEscrow::query()->where('rental_id', $rental->id)->first();
            if ($escrow && $escrow->status === RentalEscrow::STATUS_FROZEN) {
                $escrow->update([
                    'status' => $escrow->rent_released_at && $escrow->deposit_released_at
                        ? RentalEscrow::STATUS_RELEASED
                        : RentalEscrow::STATUS_PARTIALLY_RELEASED,
                ]);
            }

            $dispute->update([
                'status' => RentalDispute::STATUS_RESOLVED,
                'resolution' => $resolution,
                'capture_amount' => $captureAmount > 0 ? $captureAmount : null,
                'resolution_notes' => $notes,
                'resolved_at' => now(),
            ]);
        });
    }

    public function escrowFor(Rental $rental): ?RentalEscrow
    {
        return RentalEscrow::query()->where('rental_id', $rental->id)->first();
    }

    public function isCancellable(Rental $rental): bool
    {
        if (in_array($rental->status, [Rental::STATUS_CANCELLED, Rental::STATUS_REJECTED, Rental::STATUS_COMPLETED], true)) {
            return false;
        }

        if ($rental->started_at || $rental->status === Rental::STATUS_ACTIVE) {
            return false;
        }

        return in_array($rental->status, [Rental::STATUS_PENDING, Rental::STATUS_APPROVED], true);
    }

    public function cancelDeadline(Rental $rental): ?\Carbon\Carbon
    {
        if (! $this->isCancellable($rental)) {
            return null;
        }

        return $rental->start_date?->copy()->startOfDay();
    }
}
