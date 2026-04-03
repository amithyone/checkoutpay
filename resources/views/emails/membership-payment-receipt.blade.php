<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment receipt</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1a202c; max-width: 600px; margin: 0 auto; padding: 24px;">
    <h1 style="color: #059669; font-size: 22px;">Payment received</h1>
    <p>Hello {{ $subscription->member_name }},</p>
    <p>We have received your payment for <strong>{{ $subscription->membership->name }}</strong>. This email is your receipt.</p>

    <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0;">
        <tr><td style="padding: 12px 16px; font-size: 12px; color: #166534;">Amount paid</td></tr>
        <tr><td style="padding: 0 16px 12px; font-size: 20px; font-weight: 700;">₦{{ number_format((float) ($payment->received_amount ?? $payment->amount), 2) }}</td></tr>
        <tr><td style="padding: 12px 16px; font-size: 12px; color: #166534;">Transaction ID</td></tr>
        <tr><td style="padding: 0 16px 12px; font-family: monospace;">{{ $payment->transaction_id }}</td></tr>
        <tr><td style="padding: 12px 16px; font-size: 12px; color: #166534;">Subscription</td></tr>
        <tr><td style="padding: 0 16px 12px;">{{ $subscription->subscription_number }}</td></tr>
        <tr><td style="padding: 12px 16px; font-size: 12px; color: #166534;">Member email</td></tr>
        <tr><td style="padding: 0 16px 16px;">{{ $subscription->member_email }}</td></tr>
    </table>

    <p style="color: #4a5568; font-size: 14px;">You will receive a separate message with your full membership details and card (if applicable).</p>

    <p style="margin-top: 32px; font-size: 13px; color: #a0aec0;">{{ config('app.name') }}</p>
</body>
</html>
