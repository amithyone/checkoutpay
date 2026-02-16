<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Return Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #3C50E0 0%, #2E40C0 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: #fff; margin: 0;">Rental Return Reminder</h1>
    </div>
    <div style="background: #fff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px;">
        <p>Hello {{ $rental->renter_name }},</p>
        @if($rental->isOverdue())
            <p style="color: #b91c1c;"><strong>Your rental is overdue.</strong> Please return the gear as soon as possible to avoid additional penalties.</p>
        @else
            <p>This is a reminder that your rental is due back soon. Please return by the date below to avoid penalties.</p>
        @endif
        <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 5px 0;"><strong>Rental Number:</strong> {{ $rental->rental_number }}</p>
            <p style="margin: 5px 0;"><strong>Business:</strong> {{ $rental->business->name ?? 'N/A' }}</p>
            <p style="margin: 5px 0;"><strong>Return by:</strong> {{ $rental->returnDeadline()->format('l, M j, Y g:i A') }}</p>
        </div>
        @if($rental->business_phone)
            <p>If you need to arrange return, contact the business: <strong>{{ $rental->business_phone }}</strong></p>
        @endif
    </div>
</body>
</html>
