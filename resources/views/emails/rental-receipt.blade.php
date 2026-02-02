<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Receipt</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #3C50E0 0%, #2E40C0 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: #fff; margin: 0;">Rental Receipt</h1>
    </div>
    
    <div style="background: #fff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px;">
        <p>Hello {{ $rental->renter_name }},</p>
        
        <p>Thank you for your rental request. Here's your receipt:</p>
        
        <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 5px 0;"><strong>Rental Number:</strong> {{ $rental->rental_number }}</p>
            <p style="margin: 5px 0;"><strong>Business:</strong> {{ $rental->business->name }}</p>
            @if($rental->business_phone)
                <p style="margin: 5px 0;"><strong>Business Phone:</strong> {{ $rental->business_phone }}</p>
            @endif
            <p style="margin: 5px 0;"><strong>Period:</strong> {{ $rental->start_date->format('M d, Y') }} - {{ $rental->end_date->format('M d, Y') }}</p>
            <p style="margin: 5px 0;"><strong>Days:</strong> {{ $rental->days }}</p>
            <p style="margin: 5px 0;"><strong>Status:</strong> {{ ucfirst($rental->status) }}</p>
        </div>

        <h3 style="margin-top: 30px;">Items Rented:</h3>
        <ul style="list-style: none; padding: 0;">
            @foreach($rental->items as $item)
                <li style="background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #3C50E0;">
                    <strong>{{ $item->name }}</strong><br>
                    Quantity: {{ $item->pivot->quantity }}<br>
                    Rate: ₦{{ number_format($item->pivot->unit_rate, 2) }}/day<br>
                    Total: ₦{{ number_format($item->pivot->total_amount, 2) }}
                </li>
            @endforeach
        </ul>

        <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: right;">
            <p style="margin: 0; font-size: 18px;"><strong>Total Amount: ₦{{ number_format($rental->total_amount, 2) }}</strong></p>
        </div>

        @if($rental->business_phone)
            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <p style="margin: 0;"><strong>Contact the business:</strong> {{ $rental->business_phone }}</p>
            </div>
        @endif

        <p style="margin-top: 30px;">The business will review your request and contact you soon. You can also reach out to them directly using the phone number above.</p>
    </div>
</body>
</html>
