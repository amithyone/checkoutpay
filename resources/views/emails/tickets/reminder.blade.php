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
        .button {
            display: inline-block;
            background: #3C50E0;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⏰ Event Reminder</h1>
        </div>
        
        <div class="content">
            <p>Hello {{ $order->customer_name }},</p>
            
            <p>This is a friendly reminder that <strong>{{ $event->title }}</strong> is happening tomorrow!</p>
            
            <p><strong>Date:</strong> {{ $event->start_date->format('F d, Y') }}</p>
            <p><strong>Time:</strong> {{ $event->start_date->format('h:i A') }}</p>
            <p><strong>Venue:</strong> {{ $event->venue }}</p>
            
            <p>Don't forget to bring your tickets! You can download them using the link below:</p>
            
            <a href="{{ route('tickets.download', $order->order_number) }}" class="button">Download Tickets</a>
            
            <p>We look forward to seeing you there!</p>
        </div>
    </div>
</body>
</html>
