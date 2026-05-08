<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peer lending program — {{ $appName }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; color: #333; line-height: 1.6; }
        .wrap { max-width: 600px; margin: 0 auto; padding: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        h1 { font-size: 20px; color: #1a202c; margin-bottom: 12px; }
        .muted { color: #64748b; font-size: 14px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin: 16px 0; }
        th, td { text-align: left; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        th { color: #64748b; font-weight: 600; width: 45%; }
        .conditions { background: #f8fafc; border-radius: 8px; padding: 14px; margin-top: 16px; white-space: pre-wrap; font-size: 14px; color: #334155; }
        .cta { display: inline-block; margin-top: 20px; padding: 12px 20px; background: #3C50E0; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Hello {{ $business->name }},</h1>
        <p class="muted">Your administrator has configured your business for the <strong>peer lending (lender)</strong> program. You can publish loan offers from your dashboard when you are opted in.</p>

        <table>
            <tr><th>Max offer amount (effective now)</th><td>₦{{ number_format($rules['max_amount'], 2) }}</td></tr>
            <tr><th>Min balance to keep in account</th><td>₦{{ number_format($rules['reserve'], 2) }}</td></tr>
            <tr><th>Interest rate cap</th><td>{{ number_format($rules['max_interest'], 2) }}%</td></tr>
            <tr><th>Term allowed</th><td>{{ $rules['min_term'] }}–{{ $rules['max_term'] }} days</td></tr>
        </table>

        @if(!empty($rules['conditions']))
            <p style="font-size: 13px; font-weight: 600; color: #475569; margin-top: 8px;">Conditions from your administrator</p>
            <div class="conditions">{{ $rules['conditions'] }}</div>
        @endif

        <a href="{{ route('business.lending-offers.index') }}" class="cta">Open lending offers</a>
    </div>
</div>
</body>
</html>
