<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Ticket;
use App\Models\MembershipSubscription;
use App\Services\QRCodeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseVerificationController extends Controller
{
    /**
     * Show rental verification page (public, signed URL).
     */
    public function showRental(Request $request, Rental $rental): View
    {
        $rental->load(['business', 'items']);
        $valid = in_array($rental->status, [
            Rental::STATUS_APPROVED,
            Rental::STATUS_ACTIVE,
            Rental::STATUS_COMPLETED,
        ], true);
        $statusLabel = $rental->status;
        $message = $valid
            ? 'This rental is valid.'
            : ($rental->status === Rental::STATUS_CANCELLED || $rental->status === Rental::STATUS_REJECTED
                ? 'This rental is not valid.'
                : 'Rental status: ' . $statusLabel);

        return view('verify.show', [
            'type' => 'rental',
            'valid' => $valid,
            'message' => $message,
            'title' => 'Rental verification',
            'details' => [
                'Rental number' => $rental->rental_number,
                'Business' => $rental->business->name ?? '—',
                'Status' => ucfirst(str_replace('_', ' ', $rental->status)),
                'Start' => $rental->start_date?->format('M j, Y'),
                'End' => $rental->end_date?->format('M j, Y'),
            ],
        ]);
    }

    /**
     * Show ticket verification page (public, by verification token).
     */
    public function showTicket(Request $request, string $token): View
    {
        $ticket = Ticket::where('verification_token', $token)->with(['ticketOrder.event', 'ticketType'])->first();

        if (!$ticket) {
            return view('verify.show', [
                'type' => 'ticket',
                'valid' => false,
                'message' => 'Ticket not found.',
                'title' => 'Ticket verification',
                'details' => [],
            ]);
        }

        $order = $ticket->ticketOrder;
        $event = $order->event ?? null;
        $valid = $ticket->isValid() && !$ticket->isUsed()
            && $event && !$event->isCancelled();

        $message = !$ticket->isValid()
            ? 'Ticket is ' . $ticket->status . '.'
            : ($ticket->isUsed()
                ? 'Ticket has already been used.'
                : ($event && $event->isCancelled()
                    ? 'Event has been cancelled.'
                    : ($event && $event->end_date && $event->end_date->isPast()
                        ? 'Event has ended.'
                        : 'This ticket is valid.')));

        if ($valid) {
            $message = 'This ticket is valid.';
        }

        $details = [
            'Ticket number' => $ticket->ticket_number,
            'Type' => $ticket->ticketType->name ?? 'Ticket',
            'Status' => ucfirst($ticket->status),
        ];
        if ($event) {
            $details['Event'] = $event->title;
            $details['Date'] = $event->start_date?->format('M j, Y');
        }

        return view('verify.show', [
            'type' => 'ticket',
            'valid' => $valid,
            'message' => $message,
            'title' => 'Ticket verification',
            'details' => $details,
        ]);
    }

    /**
     * Show membership verification page (public, signed URL).
     */
    public function showMembership(Request $request, MembershipSubscription $membership): View
    {
        $membership->load('membership.business');
        $valid = $membership->isActive();
        $message = $valid
            ? 'This membership is active.'
            : ($membership->isExpired()
                ? 'This membership has expired.'
                : 'Membership status: ' . $membership->status);

        return view('verify.show', [
            'type' => 'membership',
            'valid' => $valid,
            'message' => $message,
            'title' => 'Membership verification',
            'details' => [
                'Member' => $membership->member_name,
                'Subscription' => $membership->subscription_number,
                'Membership' => $membership->membership->name ?? '—',
                'Provider' => $membership->membership->business->name ?? '—',
                'Status' => ucfirst($membership->status),
                'Expires' => $membership->expires_at?->format('M j, Y'),
            ],
        ]);
    }
}
