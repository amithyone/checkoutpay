<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .email-header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center; }
        .email-header h1 { color: #ffffff; font-size: 24px; font-weight: 700; margin-bottom: 8px; }
        .email-body { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
        .content-text { font-size: 15px; color: #4a5568; margin-bottom: 20px; line-height: 1.7; }
        .success-box { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border: 2px solid #10b981; border-radius: 12px; padding: 30px; margin: 25px 0; text-align: center; }
        .amount-highlight { background: linear-gradient(135deg, #e8edff 0%, #d6deff 100%); border: 2px solid #3C50E0; border-radius: 12px; padding: 25px; margin: 25px 0; text-align: center; }
        .amount-highlight .amount { font-size: 32px; font-weight: 700; color: #1e293b; }
        .info-box { background-color: #f7fafc; border-left: 4px solid #10b981; padding: 15px; margin: 15px 0; border-radius: 8px; }
        .info-box .label { font-size: 12px; color: #718096; text-transform: uppercase; margin-bottom: 5px; }
        .info-box .value { font-size: 16px; font-weight: 600; color: #1a202c; }
        .next-box { background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 15px 0; border-radius: 8px; }
        .email-footer { background-color: #1a202c; padding: 30px; text-align: center; color: #a0aec0; font-size: 13px; }
    </style>
</head>
<body>
    <div style="padding: 20px;">
        <div class="email-container">
            <div class="email-header">
                <h1>Payment Receipt</h1>
                <div style="color: rgba(255,255,255,0.9); font-size: 14px;">Invoice {{ $invoice->invoice_number }}</div>
            </div>
            <div class="email-body">
                <div class="greeting">Hello {{ $invoice->client_name }}!</div>
                <div class="success-box">This payment has been received and confirmed.</div>
                <div class="amount-highlight">
                    <div class="amount">{{ $invoice->currency }} {{ number_format($amount, 2) }}</div>
                    <div style="font-size: 14px; color: #475569;">Amount paid (this payment)</div>
                </div>
                <div class="info-box">
                    <div class="label">Invoice Number</div>
                    <div class="value">{{ $invoice->invoice_number }}</div>
                </div>
                <div class="info-box">
                    <div class="label">Paid To</div>
                    <div class="value">{{ $invoice->business->name }}</div>
                </div>
                <div class="info-box">
                    <div class="label">Payment Date</div>
                    <div class="value">{{ ($payment->matched_at ?? $payment->updated_at)?->format('F d, Y \a\t g:i A') ?? now()->format('F d, Y \a\t g:i A') }}</div>
                </div>
                @if($invoice->due_date)
                <div class="info-box">
                    <div class="label">Due Date (on receipt)</div>
                    <div class="value">{{ $invoice->due_date->format('F d, Y') }}</div>
                </div>
                @endif
                <div class="info-box">
                    <div class="label">Transaction Reference</div>
                    <div class="value">{{ $payment->transaction_id }}</div>
                </div>
                @if($remaining > 0)
                <div class="next-box">
                    <div class="label">Remaining balance</div>
                    <div class="value">{{ $invoice->currency }} {{ number_format($remaining, 2) }}</div>
                    @if($nextPaymentAmount !== null && $nextPaymentAmount >= 0.01)
                    <p class="content-text" style="margin-top: 10px; margin-bottom: 0;">Your next suggested payment: <strong>{{ $invoice->currency }} {{ number_format($nextPaymentAmount, 2) }}</strong>. You can pay anytime before the due date.</p>
                    @else
                    <p class="content-text" style="margin-top: 10px; margin-bottom: 0;">You can pay the remaining balance anytime. Use your invoice payment link to make the next payment.</p>
                    @endif
                </div>
                <div style="text-align: center; margin-top: 25px;">
                    <a href="{{ route('invoices.pay', $invoice->payment_link_code) }}" style="display: inline-block; background: #3C50E0; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;">Pay remaining balance</a>
                </div>
                @endif
                <div class="content-text" style="margin-top: 25px; padding: 20px; background-color: #f0f4ff; border-radius: 8px;">
                    <strong>Receipt:</strong> This email is your receipt for this payment. Please keep it for your records.
                </div>
            </div>
            <div class="email-footer">© {{ date('Y') }} {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}. All rights reserved.</div>
        </div>
    </div>
</body>
</html>
