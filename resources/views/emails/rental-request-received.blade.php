<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Rental Request</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #3C50E0 0%, #2E40C0 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: #fff; margin: 0;">New Rental Request</h1>
    </div>
    
    <div style="background: #fff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px;">
        <p>Hello {{ $rental->business->name }},</p>
        
        <p>You have received a new rental request:</p>
        
        <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 5px 0;"><strong>Rental Number:</strong> {{ $rental->rental_number }}</p>
            <p style="margin: 5px 0;"><strong>Renter:</strong> {{ $rental->renter_name }}</p>
            <p style="margin: 5px 0;"><strong>Email:</strong> {{ $rental->renter_email }}</p>
            @if($rental->renter_phone)
                <p style="margin: 5px 0;"><strong>Phone:</strong> {{ $rental->renter_phone }}</p>
            @endif
            <p style="margin: 5px 0;"><strong>Period:</strong> {{ $rental->start_date->format('M d, Y') }} - {{ $rental->end_date->format('M d, Y') }}</p>
            <p style="margin: 5px 0;"><strong>Days:</strong> {{ $rental->days }}</p>
            <p style="margin: 5px 0;"><strong>Total Amount:</strong> ₦{{ number_format($rental->total_amount, 2) }}</p>
        </div>

        <h3 style="margin-top: 30px;">Items to be Rented:</h3>
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

        @if($rental->renter_notes)
            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <strong>Renter Notes:</strong>
                <p style="margin: 5px 0;">{{ $rental->renter_notes }}</p>
            </div>
        @endif

        <div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 0;"><strong>Verified Account:</strong> {{ $rental->verified_account_name }} - {{ $rental->verified_account_number }}</p>
            <p style="margin: 5px 0 0 0;"><strong>Bank:</strong> {{ $rental->verified_bank_name }}</p>
        </div>

        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ route('business.rentals.show', $rental) }}" style="background: #3C50E0; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Rental Request</a>
        </p>
    </div>
</body>
</html>
