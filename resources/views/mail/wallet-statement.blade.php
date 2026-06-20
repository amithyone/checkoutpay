<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Account statement</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #111; line-height: 1.6; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p>Hi {{ $recipientName }},</p>
    <p>
        Your <strong>{{ $ledgerLabel }}</strong> account statement for
        <strong>{{ $periodLabel }}</strong> ({{ $from }} to {{ $to }}) is attached as a
        <strong>{{ strtoupper($format) }}</strong> file.
    </p>
    <p style="color: #555; font-size: 14px;">
        If you did not request this email, you can ignore it. For help, contact support from the CheckoutNow app.
    </p>
    <p style="color: #888; font-size: 12px; margin-top: 32px;">CheckoutNow</p>
</body>
</html>
