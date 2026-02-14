<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Withdrawal Request – {{ $appName }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #3C50E0; color: #fff; padding: 20px; border-radius: 8px 8px 0 0; }
        .body { background: #f8fafc; padding: 24px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; }
        .amount { font-size: 24px; font-weight: 700; color: #1e293b; margin: 16px 0; }
        .info { margin: 12px 0; }
        .label { font-size: 12px; color: #64748b; text-transform: uppercase; }
        .value { font-size: 15px; font-weight: 600; }
        .btn { display: inline-block; background: #3C50E0; color: #fff !important; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0; font-size: 18px;">New Withdrawal Request</h1>
        <p style="margin: 8px 0 0; opacity: 0.9;">Please review and process as soon as possible.</p>
    </div>
    <div class="body">
        <div class="amount">₦{{ number_format($withdrawal->amount, 2) }}</div>
        <div class="info">
            <div class="label">Business</div>
            <div class="value">{{ $withdrawal->business->name ?? 'N/A' }}</div>
        </div>
        <div class="info">
            <div class="label">Account</div>
            <div class="value">{{ $withdrawal->account_name }} – {{ $withdrawal->account_number }}</div>
        </div>
        <div class="info">
            <div class="label">Bank</div>
            <div class="value">{{ $withdrawal->bank_name }}</div>
        </div>
        <div class="info">
            <div class="label">Request #{{ $withdrawal->id }} · {{ $withdrawal->created_at->format('M d, Y H:i') }}</div>
        </div>
        <a href="{{ route('admin.withdrawals.show', $withdrawal) }}" class="btn">Review withdrawal</a>
    </div>
</body>
</html>
