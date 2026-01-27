<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ticket - {{ $order->order_number }}</title>
    <style>
        @page {
            margin: 0;
            size: A4;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .ticket {
            border: 2px solid #3C50E0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .ticket-header {
            text-align: center;
            border-bottom: 2px solid #3C50E0;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .ticket-title {
            font-size: 24px;
            font-weight: bold;
            color: #3C50E0;
            margin-bottom: 5px;
        }
        .ticket-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .ticket-info-left, .ticket-info-right {
            flex: 1;
        }
        .ticket-qr {
            text-align: center;
            margin: 20px 0;
        }
        .ticket-qr img {
            max-width: 200px;
            height: auto;
        }
        .ticket-footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        .ticket-number {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    @foreach($tickets as $ticketData)
        @php
            $ticket = $ticketData['ticket'];
            $qrCode = $ticketData['qr_code'];
        @endphp
        <div class="ticket">
            <div class="ticket-header">
                <div class="ticket-title">{{ $event->title }}</div>
                <div style="font-size: 14px; color: #666;">{{ $event->venue }}</div>
            </div>

            <div class="ticket-number">
                Ticket #{{ $ticket->ticket_number }}
            </div>

            <div class="ticket-info">
                <div class="ticket-info-left">
                    <div><strong>Date:</strong> {{ $event->start_date->format('F d, Y') }}</div>
                    <div><strong>Time:</strong> {{ $event->start_date->format('h:i A') }}</div>
                    <div><strong>Venue:</strong> {{ $event->venue }}</div>
                </div>
                <div class="ticket-info-right">
                    <div><strong>Ticket Type:</strong> {{ $ticket->ticketType->name }}</div>
                    <div><strong>Price:</strong> ₦{{ number_format($ticket->ticketType->price, 2) }}</div>
                    <div><strong>Customer:</strong> {{ $order->customer_name }}</div>
                </div>
            </div>

            <div class="ticket-qr">
                @if($qrCode)
                    <img src="{{ $qrCode }}" alt="QR Code">
                @endif
            </div>

            <div class="ticket-footer">
                <div>Order #{{ $order->order_number }}</div>
                <div>Please present this ticket at the event entrance</div>
                <div style="margin-top: 5px;">This ticket is valid for one person only</div>
            </div>
        </div>
    @endforeach
</body>
</html>
