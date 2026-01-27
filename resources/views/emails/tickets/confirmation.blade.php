<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #3C50E0;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .ticket-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background: #3C50E0;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎫 Your Tickets Are Ready!</h1>
        </div>
        
        <div class="content">
            <p>Hello {{ $order->customer_name }},</p>
            
            <p>Thank you for your purchase! Your tickets for <strong>{{ $event->title }}</strong> are confirmed.</p>
            
            <div class="ticket-info">
                <h2>Event Details</h2>
                <p><strong>Date:</strong> {{ $event->start_date->format('F d, Y') }}</p>
                <p><strong>Time:</strong> {{ $event->start_date->format('h:i A') }}</p>
                <p><strong>Venue:</strong> {{ $event->venue }}</p>
                <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
                <p><strong>Total Amount:</strong> ₦{{ number_format($order->total_amount, 2) }}</p>
            </div>

            <div class="ticket-info">
                <h3>Your Tickets ({{ $tickets->count() }})</h3>
                <ul>
                    @foreach($tickets as $ticket)
                        <li>{{ $ticket->ticketType->name }} - {{ $ticket->ticket_number }}</li>
                    @endforeach
                </ul>
            </div>

            <p>Your tickets are attached to this email as a PDF. Please download and print them, or show them on your mobile device at the event entrance.</p>
            
            <p>You can also download your tickets anytime by clicking the button below:</p>
            
            <a href="{{ route('tickets.download', $order->order_number) }}" class="button">Download Tickets</a>
            
            <div class="footer">
                <p>If you have any questions, please contact us.</p>
                <p>See you at the event!</p>
            </div>
        </div>
    </div>
</body>
</html>
