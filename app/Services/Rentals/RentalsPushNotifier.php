<?php

namespace App\Services\Rentals;

use App\Models\Rental;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;

/**
 * High-level rentals push helpers — always emit contract-shaped `data` for deep links.
 */
final class RentalsPushNotifier
{
    public function __construct(
        private PushNotificationService $push
    ) {}

    public function rentalCreated(Rental $rental): void
    {
        $this->safe(function () use ($rental) {
            $this->push->notifyBusiness(
                (int) $rental->business_id,
                'New rental request',
                'You have a new rental request to review.',
                RentalsPushContract::rentalPayload(
                    RentalsPushContract::TYPE_RENTAL_REQUEST_NEW,
                    RentalsPushContract::ROLE_BUSINESS,
                    $rental
                )
            );
        }, 'rental_created', $rental);
    }

    public function statusChanged(Rental $rental, string $previousStatus): void
    {
        $status = strtolower((string) $rental->status);
        $prev = strtolower($previousStatus);

        if ($status === $prev) {
            return;
        }

        match ($status) {
            Rental::STATUS_APPROVED => $this->notifyRenterRental(
                $rental,
                RentalsPushContract::TYPE_RENTAL_APPROVED,
                'Rental approved',
                'Your rental request has been approved.'
            ),
            Rental::STATUS_REJECTED => $this->notifyRenterRental(
                $rental,
                RentalsPushContract::TYPE_RENTAL_DENIED,
                'Rental denied',
                'Your rental request was denied.'
            ),
            Rental::STATUS_CANCELLED => $this->notifyBothRental(
                $rental,
                RentalsPushContract::TYPE_RENTAL_CANCELLED,
                'Rental cancelled',
                'A rental was cancelled.'
            ),
            Rental::STATUS_ACTIVE => $this->notifyBothRental(
                $rental,
                RentalsPushContract::TYPE_RENTAL_ACTIVE,
                'Rental active',
                'Pickup confirmed — rental is now active.'
            ),
            Rental::STATUS_COMPLETED => $this->notifyBothRental(
                $rental,
                RentalsPushContract::TYPE_GENERIC,
                'Rental completed',
                'Your rental has been completed.',
                // Prefer order detail for completed
            ),
            default => null,
        };
    }

    public function pickupOrReturnRequested(Rental $rental): void
    {
        $this->safe(function () use ($rental) {
            $this->push->notifyBusiness(
                (int) $rental->business_id,
                'Pickup / return request',
                'A renter requested pickup or return on a rental.',
                RentalsPushContract::rentalPayload(
                    RentalsPushContract::TYPE_PICKUP_REQUEST,
                    RentalsPushContract::ROLE_BUSINESS,
                    $rental
                )
            );
        }, 'pickup_request', $rental);
    }

    public function delivered(Rental $rental): void
    {
        $this->notifyBothRental(
            $rental,
            RentalsPushContract::TYPE_RENTAL_DELIVERED,
            'Rental out for delivery',
            'Delivery status updated for your rental.'
        );
    }

    public function returnDueSoon(Rental $rental): void
    {
        $this->notifyBothRental(
            $rental,
            RentalsPushContract::TYPE_RETURN_DUE_SOON,
            'Return due soon',
            'Your rental return time is coming up soon.'
        );
    }

    public function returnOverdue(Rental $rental): void
    {
        $this->notifyBothRental(
            $rental,
            RentalsPushContract::TYPE_RETURN_OVERDUE,
            'Return overdue',
            'Your rental return time has passed.'
        );
    }

    public function walletCredit(int $renterId, float $amount, ?int $paymentId = null): void
    {
        $this->safe(function () use ($renterId, $amount, $paymentId) {
            $extra = [];
            if ($paymentId) {
                $extra['payment_id'] = $paymentId;
                $extra['transactionId'] = $paymentId;
                $extra['transaction_id'] = $paymentId;
            }
            $this->push->notifyRenter(
                $renterId,
                'Wallet credited',
                'Your wallet has been credited.',
                RentalsPushContract::walletPayload(
                    RentalsPushContract::TYPE_WALLET_CREDIT,
                    RentalsPushContract::ROLE_RENTER,
                    $amount,
                    $extra
                )
            );
        }, 'wallet_credit');
    }

    public function walletDebit(int $renterId, float $amount): void
    {
        $this->safe(function () use ($renterId, $amount) {
            $this->push->notifyRenter(
                $renterId,
                'Wallet debited',
                'Your wallet was debited for a rental payment.',
                RentalsPushContract::walletPayload(
                    RentalsPushContract::TYPE_WALLET_DEBIT,
                    RentalsPushContract::ROLE_RENTER,
                    $amount
                )
            );
        }, 'wallet_debit');
    }

    public function supportMessageToBusiness(int $businessId, int $ticketId): void
    {
        $this->safe(function () use ($businessId, $ticketId) {
            $this->push->notifyBusiness(
                $businessId,
                'New support message',
                'Support has replied to your ticket.',
                RentalsPushContract::supportPayload(RentalsPushContract::ROLE_BUSINESS, $ticketId)
            );
        }, 'support_message');
    }

    public function supportMessageToRenter(int $renterId, int $ticketId): void
    {
        $this->safe(function () use ($renterId, $ticketId) {
            $this->push->notifyRenter(
                $renterId,
                'New support message',
                'Support has replied to your ticket.',
                RentalsPushContract::supportPayload(RentalsPushContract::ROLE_RENTER, $ticketId)
            );
        }, 'support_message_renter');
    }

    public function disputeUpdate(Rental $rental, string $body = 'A dispute was updated on your rental.'): void
    {
        $this->notifyBothRental(
            $rental,
            RentalsPushContract::TYPE_DISPUTE_UPDATE,
            'Dispute update',
            $body
        );
    }

    protected function notifyRenterRental(Rental $rental, string $type, string $title, string $body): void
    {
        $this->safe(function () use ($rental, $type, $title, $body) {
            $this->push->notifyRenter(
                (int) $rental->renter_id,
                $title,
                $body,
                RentalsPushContract::rentalPayload($type, RentalsPushContract::ROLE_RENTER, $rental)
            );
        }, $type, $rental);
    }

    protected function notifyBothRental(Rental $rental, string $type, string $title, string $body): void
    {
        $this->safe(function () use ($rental, $type, $title, $body) {
            $this->push->notifyRenter(
                (int) $rental->renter_id,
                $title,
                $body,
                RentalsPushContract::rentalPayload($type, RentalsPushContract::ROLE_RENTER, $rental)
            );
            $this->push->notifyBusiness(
                (int) $rental->business_id,
                $title,
                $body,
                RentalsPushContract::rentalPayload($type, RentalsPushContract::ROLE_BUSINESS, $rental)
            );
        }, $type, $rental);
    }

    /**
     * @param  callable():void  $fn
     */
    protected function safe(callable $fn, string $event, ?Rental $rental = null): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('rentals.push.failed', [
                'event' => $event,
                'rental_id' => $rental?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
