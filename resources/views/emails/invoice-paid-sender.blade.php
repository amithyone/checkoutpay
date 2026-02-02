<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Paid - {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .email-header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center; }
        .email-header h1 { color: #ffffff; font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .email-header .subtitle { color: rgba(255, 255, 255, 0.9); font-size: 14px; }
        .email-body { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
        .content-text { font-size: 15px; color: #4a5568; margin-bottom: 20px; line-height: 1.7; }
        .success-box { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border: 2px solid #10b981; border-radius: 12px; padding: 30px; margin: 25px 0; text-align: center; }
        .success-box .icon { font-size: 48px; margin-bottom: 15px; }
        .success-box .message { font-size: 20px; font-weight: 600; color: #065f46; margin-bottom: 10px; }
        .amount-highlight { background: linear-gradient(135deg, #e8edff 0%, #d6deff 100%); border: 2px solid #3C50E0; border-radius: 12px; padding: 25px; margin: 25px 0; text-align: center; }
        .amount-highlight .amount { font-size: 32px; font-weight: 700; color: #1e293b; margin-bottom: 5px; }
        .amount-highlight .label { font-size: 14px; color: #475569; }
        .info-box { background-color: #f7fafc; border-left: 4px solid #10b981; padding: 15px; margin: 15px 0; border-radius: 8px; }
        .info-box .label { font-size: 12px; color: #718096; text-transform: uppercase; margin-bottom: 5px; }
        .info-box .value { font-size: 16px; font-weight: 600; color: #1a202c; }
        .email-footer { background-color: #1a202c; padding: 30px; text-align: center; }
        .email-footer .footer-text { color: #a0aec0; font-size: 13px; }
    </style>
</head>
<body>
    <div style="padding: 20px;">
        <div class="email-container">
            <div class="email-header">
                <h1>🎉 Invoice Paid!</h1>
                <div class="subtitle">Payment Received</div>
            </div>
            <div class="email-body">
                <div class="greeting">Hello {{ $invoice->business->name }}!</div>
                <div class="success-box">
                    <div class="icon">✅</div>
                    <div class="message">Payment Received Successfully</div>
                    <div style="color: #047857; font-size: 14px;">Invoice #{{ $invoice->invoice_number }} has been paid</div>
                </div>
                <div class="content-text">
                    Great news! <strong>{{ $invoice->client_name }}</strong> has successfully paid invoice #{{ $invoice->invoice_number }}.
                </div>
                <div class="amount-highlight">
                    <div class="amount">{{ $invoice->currency }} {{ number_format($invoice->paid_amount ?? $invoice->total_amount, 2) }}</div>
                    <div class="label">Amount Received</div>
                </div>
                <div class="info-box">
                    <div class="label">Paid By</div>
                    <div class="value">{{ $invoice->client_name }}</div>
                </div>
                <div class="info-box">
                    <div class="label">Payment Date</div>
                    <div class="value">{{ ($invoice->paid_at ?? now())->format('F d, Y \a\t g:i A') }}</div>
                </div>
                @if($invoice->payment)
                <div class="info-box">
                    <div class="label">Transaction ID</div>
                    <div class="value">{{ $invoice->payment->transaction_id }}</div>
                </div>
                @endif
                <div style="text-align: center; margin-top: 30px;">
                    <a href="{{ route('business.invoices.show', $invoice) }}" style="display: inline-block; background: linear-gradient(135deg, #3C50E0 0%, #2E40C7 100%); color: #ffffff !important; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px;">View Invoice</a>
                </div>
            </div>
            <div class="email-footer">
                <div class="footer-text">© {{ date('Y') }} {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}. All rights reserved.</div>
            </div>
        </div>
    </div>
</body>
</html>
