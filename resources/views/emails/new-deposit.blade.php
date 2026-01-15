<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Payment Received - {{ $appName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .email-header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .email-header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin-bottom: 10px; }
        .email-body { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
        .content-text { font-size: 15px; color: #4a5568; margin-bottom: 20px; line-height: 1.7; }
        .amount-box { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border: 2px solid #10b981; border-radius: 12px; padding: 30px; margin: 30px 0; text-align: center; }
        .amount-box .icon { width: 64px; height: 64px; background: #10b981; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #ffffff; }
        .amount-box .amount { font-size: 36px; font-weight: 700; color: #065f46; margin-bottom: 10px; }
        .amount-box .label { font-size: 14px; color: #065f46; }
        .info-grid { display: grid; gap: 15px; margin: 25px 0; }
        .info-item { background-color: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #10b981; }
        .info-item .label { font-size: 12px; color: #718096; text-transform: uppercase; margin-bottom: 5px; }
        .info-item .value { font-size: 16px; font-weight: 600; color: #1a202c; }
        .balance-box { background-color: #f0fdf4; border: 2px solid #10b981; border-radius: 8px; padding: 20px; margin: 25px 0; text-align: center; }
        .balance-box .label { font-size: 14px; color: #065f46; margin-bottom: 5px; }
        .balance-box .balance { font-size: 24px; font-weight: 700; color: #059669; }
        .email-footer { background-color: #1a202c; padding: 30px; text-align: center; border-radius: 0 0 8px 8px; }
        .email-footer .footer-text { color: #a0aec0; font-size: 13px; }
        @media only screen and (max-width: 600px) {
            .email-body { padding: 30px 20px; }
            .email-header { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <div style="padding: 20px;">
        <div class="email-container">
            <div class="email-header">
                <h1>{{ $appName }}</h1>
                <div style="color: rgba(255, 255, 255, 0.9); font-size: 14px;">New Payment Received</div>
            </div>
            <div class="email-body">
                <div class="greeting">Hello {{ $business->name }}!</div>
                <div class="content-text">Great news! You've received a new payment that has been approved and credited to your account.</div>
                <div class="amount-box">
                    <div class="icon">💰</div>
                    <div class="amount">₦{{ number_format($payment->amount, 2) }}</div>
                    <div class="label">Payment Received</div>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Transaction ID</div>
                        <div class="value">{{ $payment->transaction_id }}</div>
                    </div>
                    @if($payment->payer_name)
                    <div class="info-item">
                        <div class="label">From</div>
                        <div class="value">{{ $payment->payer_name }}</div>
                    </div>
                    @endif
                    @if($payment->account_number)
                    <div class="info-item">
                        <div class="label">Account Number</div>
                        <div class="value">{{ $payment->account_number }}</div>
                    </div>
                    @endif
                    @if($payment->website)
                    <div class="info-item">
                        <div class="label">Website</div>
                        <div class="value" style="word-break: break-all;">{{ $payment->website->website_url }}</div>
                    </div>
                    @endif
                    <div class="info-item">
                        <div class="label">Approved Date</div>
                        <div class="value">{{ $payment->approved_at ? $payment->approved_at->format('F d, Y \a\t g:i A') : $payment->created_at->format('F d, Y \a\t g:i A') }}</div>
                    </div>
                </div>
                <div class="balance-box">
                    <div class="label">New Account Balance</div>
                    <div class="balance">₦{{ number_format($business->balance, 2) }}</div>
                </div>
                <div class="content-text">The payment has been successfully processed and added to your account balance. You can now request a withdrawal or continue accepting payments.</div>
                <div style="text-align: center;">
                    <a href="{{ route('business.transactions.show', $payment) }}" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff !important; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 20px 0;">View Transaction</a>
                </div>
            </div>
            <div class="email-footer">
                <div class="footer-text">© {{ date('Y') }} {{ $appName }}. All rights reserved.</div>
            </div>
        </div>
    </div>
</body>
</html>
