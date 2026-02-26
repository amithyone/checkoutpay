<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Approved – Please Pay</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #3C50E0 0%, #2E40C0 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: #fff; margin: 0;">Rental Approved</h1>
    </div>

    <div style="background: #fff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px;">
        <p>Hello {{ $rental->renter_name }},</p>

        <p>Your rental request <strong>{{ $rental->rental_number }}</strong> has been approved by {{ $rental->business->name }}.</p>

        <p><strong>Please complete payment to confirm your rental.</strong></p>

        <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
            <p style="margin: 5px 0;"><strong>Total: ₦{{ number_format($rental->total_amount, 2) }}</strong></p>
            <p style="margin: 15px 0 5px 0;">Period: {{ $rental->start_date->format('M d, Y') }} – {{ $rental->end_date->format('M d, Y') }}</p>
        </div>

        <p style="text-align: center; margin: 25px 0;">
            <a href="{{ $payUrl }}" style="display: inline-block; background: #3C50E0; color: #fff; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold;">Pay Now</a>
        </p>

        <p style="font-size: 13px; color: #666;">Or copy this link: {{ $payUrl }}</p>

        <p style="margin-top: 25px;">If you have any questions, contact {{ $rental->business->name }}@if($rental->business_phone) at {{ $rental->business_phone }}@endif.</p>
    </div>
</body>
</html>
