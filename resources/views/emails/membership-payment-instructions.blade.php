<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Membership payment</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1a202c; max-width: 600px; margin: 0 auto; padding: 24px;">
    <h1 style="color: #059669; font-size: 22px;">Hi {{ $member['member_name'] }},</h1>
    <p>Thanks for signing up for <strong>{{ $membership->name }}</strong>. Use the bank details below to complete your transfer.</p>

    <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #f7fafc; border-radius: 8px;">
        <tr><td style="padding: 12px 16px; font-size: 12px; color: #718096; text-transform: uppercase;">Amount</td></tr>
        <tr><td style="padding: 0 16px 12px; font-size: 20px; font-weight: 700;">₦{{ number_format((float) $payment->amount, 2) }}</td></tr>
        <tr><td style="padding: 12px 16px; font-size: 12px; color: #718096; text-transform: uppercase;">Bank</td></tr>
        <tr><td style="padding: 0 16px 12px; font-weight: 600;">{{ $payment->accountNumberDetails->bank_name ?? '—' }}</td></tr>
        <tr><td style="padding: 12px 16px; font-size: 12px; color: #718096; text-transform: uppercase;">Account number</td></tr>
        <tr><td style="padding: 0 16px 12px; font-size: 18px; font-weight: 700; letter-spacing: 1px;">{{ $payment->account_number }}</td></tr>
        <tr><td style="padding: 12px 16px; font-size: 12px; color: #718096; text-transform: uppercase;">Account name</td></tr>
        <tr><td style="padding: 0 16px 12px; font-weight: 600;">{{ $payment->accountNumberDetails->account_name ?? '—' }}</td></tr>
        <tr><td style="padding: 12px 16px; font-size: 12px; color: #718096; text-transform: uppercase;">Reference / transaction ID</td></tr>
        <tr><td style="padding: 0 16px 16px; font-family: monospace; font-size: 14px;">{{ $payment->transaction_id }}</td></tr>
    </table>

    @if($payment->expires_at)
        <p style="color: #4a5568; font-size: 14px;">Please pay before <strong>{{ $payment->expires_at->timezone(config('app.timezone'))->format('M d, Y H:i') }}</strong> (your payment may expire after that).</p>
    @endif

    <p style="color: #4a5568; font-size: 14px;">When your payment is confirmed, you will receive a separate email with your membership activation and receipt.</p>

    <p style="margin-top: 32px; font-size: 13px; color: #a0aec0;">{{ config('app.name') }}</p>
</body>
</html>
