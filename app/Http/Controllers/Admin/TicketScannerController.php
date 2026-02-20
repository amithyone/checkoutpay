<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\QRCodeService;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TicketScannerController extends Controller
{
    public function __construct(
        protected QRCodeService $qrCodeService,
        protected TicketService $ticketService
    ) {}

    /**
     * Display QR code scanner interface
     */
    public function index()
    {
        return view('admin.tickets.scanner');
    }

    /**
     * Verify QR code data
     */
    public function verify(Request $request)
    {
        $request->validate([
            'qr_data' => 'required|string',
        ]);

        try {
            $qrDataRaw = $request->qr_data;
            $verification = null;

            // If QR contains verification page URL, verify by token
            if (is_string($qrDataRaw) && (str_starts_with($qrDataRaw, 'http') || str_starts_with($qrDataRaw, '/')) && str_contains($qrDataRaw, '/verify/ticket/')) {
                $rest = trim(explode('/verify/ticket/', $qrDataRaw, 2)[1] ?? '');
                $token = trim(explode('?', $rest)[0]);
                if ($token !== '') {
                    $verification = $this->qrCodeService->verifyByToken($token);
                }
            }

            if (!$verification) {
                $qrData = is_string($qrDataRaw) ? json_decode($qrDataRaw, true) : $qrDataRaw;
                if (!is_array($qrData)) {
                    return response()->json([
                        'valid' => false,
                        'message' => 'Invalid QR code format',
                    ], 400);
                }
                $verification = $this->qrCodeService->verify($qrData);
            }

            if (!$verification['valid']) {
                return response()->json([
                    'valid' => false,
                    'message' => $verification['message'],
                    'ticket' => $verification['ticket'] ? [
                        'ticket_number' => $verification['ticket']->ticket_number,
                        'status' => $verification['ticket']->status,
                    ] : null,
                ]);
            }

            $ticket = $verification['ticket'];
            $order = $ticket->ticketOrder;
            $event = $order->event;

            return response()->json([
                'valid' => true,
                'message' => 'Ticket is valid',
                'ticket' => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'event_title' => $event->title,
                    'venue' => $event->venue,
                    'ticket_type' => $ticket->ticketType->name,
                    'status' => $ticket->status,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('QR code verification error', [
                'error' => $e->getMessage(),
                'qr_data' => $request->qr_data,
            ]);

            return response()->json([
                'valid' => false,
                'message' => 'Error verifying ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check in ticket
     */
    public function checkIn(Request $request)
    {
        $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $ticket = Ticket::findOrFail($request->ticket_id);
            $admin = auth('admin')->user();

            $this->ticketService->checkInTicket(
                $ticket,
                $admin,
                'qr_scan',
                $request->location,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Ticket checked in successfully',
                'ticket' => [
                    'ticket_number' => $ticket->ticket_number,
                    'checked_in_at' => $ticket->checked_in_at->toDateTimeString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Manual check-in by ticket number
     */
    public function manualCheckIn(Request $request)
    {
        $request->validate([
            'ticket_number' => 'required|string',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $ticket = Ticket::where('ticket_number', $request->ticket_number)->firstOrFail();
            $admin = auth('admin')->user();

            if (!$ticket->canBeCheckedIn()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket cannot be checked in',
                ], 400);
            }

            $this->ticketService->checkInTicket(
                $ticket,
                $admin,
                'manual',
                $request->location,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Ticket checked in successfully',
                'ticket' => [
                    'ticket_number' => $ticket->ticket_number,
                    'checked_in_at' => $ticket->checked_in_at->toDateTimeString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
