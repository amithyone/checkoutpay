<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Tickets - {{ $event->title }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .ticket { border: 2px dashed #3C50E0; padding: 20px; margin: 20px 0; background: #f9fafb; }
        .ticket-header { text-align: center; margin-bottom: 20px; }
        .qr-code { text-align: center; margin: 20px 0; }
        .ticket-info { margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Your Tickets for {{ $event->title }}</h1>
        <p>Hello {{ $order->customer_name }},</p>
        <p>Thank you for your purchase! Your tickets are attached below.</p>

        @foreach($tickets as $ticket)
            <div class="ticket">
                <div class="ticket-header">
                    <h2>{{ $event->title }}</h2>
                    <p>Ticket #{{ $ticket->ticket_number }}</p>
                </div>
                
                <div class="ticket-info">
                    <p><strong>Attendee:</strong> {{ $ticket->attendee_name }}</p>
                    <p><strong>Email:</strong> {{ $ticket->attendee_email }}</p>
                    <p><strong>Ticket Type:</strong> {{ $ticket->ticketType->name }}</p>
                    <p><strong>Event Date:</strong> {{ $event->start_date->format('l, F d, Y h:i A') }}</p>
                    @if($event->venue_name)
                        <p><strong>Venue:</strong> {{ $event->venue_name }}</p>
                    @endif
                </div>

                @if($ticket->qr_code)
                    <div class="qr-code">
                        <img src="{{ $ticket->qr_code }}" alt="QR Code" style="max-width: 200px;">
                        <p style="font-size: 12px; color: #666;">Present this QR code at the event</p>
                    </div>
                @endif
            </div>
        @endforeach

        <p>We look forward to seeing you at the event!</p>
        <p>Best regards,<br>{{ $event->organizer_name ?? $event->business->name }}</p>
    </div>
</body>
</html>
