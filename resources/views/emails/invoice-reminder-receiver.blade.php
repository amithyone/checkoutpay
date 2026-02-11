<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Reminder - {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 30px; text-align: center; color: #fff; }
        .body { padding: 30px; }
        .amount { font-size: 24px; font-weight: 700; color: #1e293b; margin: 15px 0; }
        .info-box { background: #f7fafc; border-left: 4px solid #3C50E0; padding: 15px; margin: 15px 0; border-radius: 8px; }
        .btn { display: inline-block; background: #3C50E0; color: #fff !important; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; margin-top: 20px; }
        .footer { background: #1a202c; padding: 20px; text-align: center; color: #a0aec0; font-size: 13px; }
    </style>
</head>
<body>
    <div style="padding: 20px;">
        <div class="container">
            <div class="header">
                @if($isOverdue)
                <h1 style="margin: 0; font-size: 22px;">Invoice overdue</h1>
                @else
                <h1 style="margin: 0; font-size: 22px;">Invoice due soon</h1>
                @endif
                <p style="margin: 8px 0 0; opacity: 0.95;">Invoice {{ $invoice->invoice_number }}</p>
            </div>
            <div class="body">
                <p>Hello {{ $invoice->client_name }},</p>
                @if($isOverdue)
                <p>This is a friendly reminder that invoice <strong>{{ $invoice->invoice_number }}</strong> from <strong>{{ $invoice->business->name }}</strong> is <strong>overdue</strong>.</p>
                @else
                <p>This is a friendly reminder that invoice <strong>{{ $invoice->invoice_number }}</strong> from <strong>{{ $invoice->business->name }}</strong> is due soon.</p>
                @endif
                @php $remaining = (float) $invoice->total_amount - (float) ($invoice->paid_amount ?? 0); @endphp
                <div class="amount">{{ $invoice->currency }} {{ number_format($remaining > 0 ? $remaining : $invoice->total_amount, 2) }}</div>
                @if($remaining > 0 && (float)($invoice->paid_amount ?? 0) > 0)
                <p style="color: #64748b; font-size: 14px;">Remaining balance (you have already paid {{ $invoice->currency }} {{ number_format($invoice->paid_amount, 2) }}).</p>
                @endif
                @if($invoice->due_date)
                <div class="info-box">
                    <strong>Due date:</strong> {{ $invoice->due_date->format('F d, Y') }}
                </div>
                @endif
                <p>You can pay securely using the link below.</p>
                <a href="{{ route('invoices.pay', $invoice->payment_link_code) }}" class="btn">Pay invoice</a>
            </div>
            <div class="footer">© {{ date('Y') }} {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}. All rights reserved.</div>
        </div>
    </div>
</body>
</html>
