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
        .footer { background: #1a202c; padding: 20px; text-align: center; color: #a0aec0; font-size: 13px; }
    </style>
</head>
<body>
    <div style="padding: 20px;">
        <div class="container">
            <div class="header">
                @if($isOverdue)
                <h1 style="margin: 0; font-size: 22px;">Invoice overdue (client)</h1>
                <p style="margin: 8px 0 0; opacity: 0.95;">Invoice {{ $invoice->invoice_number }}</p>
                @else
                <h1 style="margin: 0; font-size: 22px;">Invoice due soon (client)</h1>
                <p style="margin: 8px 0 0; opacity: 0.95;">Invoice {{ $invoice->invoice_number }}</p>
                @endif
            </div>
            <div class="body">
                <p>Hello {{ $invoice->business->name }},</p>
                @if($isOverdue)
                <p>This is a reminder that your invoice <strong>{{ $invoice->invoice_number }}</strong> to <strong>{{ $invoice->client_name }}</strong> is <strong>overdue</strong>.</p>
                @else
                <p>This is a reminder that your invoice <strong>{{ $invoice->invoice_number }}</strong> to <strong>{{ $invoice->client_name }}</strong> is due soon.</p>
                @endif
                <div class="amount">{{ $invoice->currency }} {{ number_format($invoice->total_amount, 2) }}</div>
                @if($invoice->due_date)
                <div class="info-box">
                    <strong>Due date:</strong> {{ $invoice->due_date->format('F d, Y') }}
                </div>
                @endif
                <p>A reminder has been sent to your client. You can also share the payment link if needed.</p>
            </div>
            <div class="footer">© {{ date('Y') }} {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}. All rights reserved.</div>
        </div>
    </div>
</body>
</html>
