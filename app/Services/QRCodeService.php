<?php

namespace App\Services;

use App\Models\Ticket;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QRCodeService
{
    /**
     * Generate QR code for a ticket
     *
     * @param Ticket $ticket
     * @return array Returns ['path' => storage path, 'data' => QR data array]
     */
    public function generateForTicket(Ticket $ticket): array
    {
        // Prepare QR code data
        $qrData = [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'event_id' => $ticket->ticketOrder->event_id,
            'order_id' => $ticket->ticket_order_id,
            'verification_token' => $ticket->verification_token,
            'expires_at' => $ticket->ticketOrder->event->end_date->toIso8601String(),
        ];

        // Encode data as JSON
        $qrDataString = json_encode($qrData);

        // Generate QR code (PNG format, 300x300px)
        $qrCodePath = 'tickets/qr-codes/' . $ticket->ticket_number . '.png';
        
        // Create directory if it doesn't exist
        Storage::disk('public')->makeDirectory('tickets/qr-codes');

        // Generate QR code image
        QrCode::format('png')
            ->size(300)
            ->margin(2)
            ->generate($qrDataString, Storage::disk('public')->path($qrCodePath));

        // Update ticket with QR code info
        $ticket->update([
            'qr_code' => $qrCodePath,
            'qr_code_data' => $qrData,
        ]);

        return [
            'path' => $qrCodePath,
            'url' => Storage::disk('public')->url($qrCodePath),
            'data' => $qrData,
        ];
    }

    /**
     * Verify QR code data
     *
     * @param array $qrData
     * @return array Returns ['valid' => bool, 'ticket' => Ticket|null, 'message' => string]
     */
    public function verify(array $qrData): array
    {
        // Validate required fields
        if (!isset($qrData['ticket_id']) || !isset($qrData['verification_token'])) {
            return [
                'valid' => false,
                'ticket' => null,
                'message' => 'Invalid QR code data',
            ];
        }

        // Find ticket
        $ticket = Ticket::where('id', $qrData['ticket_id'])
            ->where('verification_token', $qrData['verification_token'])
            ->first();

        if (!$ticket) {
            return [
                'valid' => false,
                'ticket' => null,
                'message' => 'Ticket not found',
            ];
        }

        // Check if ticket is valid
        if (!$ticket->isValid()) {
            return [
                'valid' => false,
                'ticket' => $ticket,
                'message' => 'Ticket is ' . $ticket->status,
            ];
        }

        // Check if ticket is already used
        if ($ticket->isUsed()) {
            return [
                'valid' => false,
                'ticket' => $ticket,
                'message' => 'Ticket has already been used',
            ];
        }

        // Check if event is cancelled
        if ($ticket->ticketOrder->event->isCancelled()) {
            return [
                'valid' => false,
                'ticket' => $ticket,
                'message' => 'Event has been cancelled',
            ];
        }

        // Check expiration
        if (isset($qrData['expires_at'])) {
            $expiresAt = \Carbon\Carbon::parse($qrData['expires_at']);
            if ($expiresAt < now()) {
                return [
                    'valid' => false,
                    'ticket' => $ticket,
                    'message' => 'Ticket has expired',
                ];
            }
        }

        return [
            'valid' => true,
            'ticket' => $ticket,
            'message' => 'Ticket is valid',
        ];
    }

    /**
     * Get QR code image URL
     *
     * @param Ticket $ticket
     * @return string|null
     */
    public function getQrCodeUrl(Ticket $ticket): ?string
    {
        if (!$ticket->qr_code) {
            return null;
        }

        return Storage::disk('public')->url($ticket->qr_code);
    }

    /**
     * Generate QR code as base64 for embedding in PDF
     *
     * @param Ticket $ticket
     * @return string|null
     */
    public function generateBase64(Ticket $ticket): ?string
    {
        if (!$ticket->qr_code_data) {
            $this->generateForTicket($ticket);
        }

        $qrDataString = json_encode($ticket->qr_code_data);

        // Generate QR code as base64
        $qrCode = QrCode::format('png')
            ->size(200)
            ->margin(1)
            ->generate($qrDataString);

        return 'data:image/png;base64,' . base64_encode($qrCode);
    }
}
