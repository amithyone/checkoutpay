<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Request Submitted - {{ $appName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .email-header { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); padding: 40px 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .email-header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin-bottom: 10px; }
        .email-body { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
        .content-text { font-size: 15px; color: #4a5568; margin-bottom: 20px; line-height: 1.7; }
        .amount-box { background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%); border: 2px solid #8b5cf6; border-radius: 12px; padding: 30px; margin: 30px 0; text-align: center; }
        .amount-box .amount { font-size: 36px; font-weight: 700; color: #6d28d9; margin-bottom: 10px; }
        .amount-box .label { font-size: 14px; color: #5b21b6; }
        .info-grid { display: grid; gap: 15px; margin: 25px 0; }
        .info-item { background-color: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #8b5cf6; }
        .info-item .label { font-size: 12px; color: #718096; text-transform: uppercase; margin-bottom: 5px; }
        .info-item .value { font-size: 16px; font-weight: 600; color: #1a202c; }
        .status-badge { display: inline-block; background: #fbbf24; color: #78350f; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 10px; }
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
                <div style="color: rgba(255, 255, 255, 0.9); font-size: 14px;">Withdrawal Request Submitted</div>
            </div>
            <div class="email-body">
                <div class="greeting">Hello {{ $business->name }}!</div>
                <div class="content-text">Your withdrawal request has been submitted successfully and is now pending review.</div>
                <div class="amount-box">
                    <div class="amount">₦{{ number_format($withdrawal->amount, 2) }}</div>
                    <div class="label">Withdrawal Amount</div>
                    <div class="status-badge">Pending Review</div>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">Request ID</div>
                        <div class="value">#{{ $withdrawal->id }}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Bank Name</div>
                        <div class="value">{{ $withdrawal->bank_name }}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Account Name</div>
                        <div class="value">{{ $withdrawal->account_name }}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Account Number</div>
                        <div class="value">{{ $withdrawal->account_number }}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">Submitted Date</div>
                        <div class="value">{{ $withdrawal->created_at->format('F d, Y \a\t g:i A') }}</div>
                    </div>
                </div>
                <div class="content-text">We'll review your request and notify you once it's been processed. This usually takes 1-2 business days.</div>
                <div style="text-align: center;">
                    <a href="{{ route('business.withdrawals.show', $withdrawal) }}" style="display: inline-block; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: #ffffff !important; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 20px 0;">View Request</a>
                </div>
            </div>
            <div class="email-footer">
                <div class="footer-text">© {{ date('Y') }} {{ $appName }}. All rights reserved.</div>
            </div>
        </div>
    </div>
</body>
</html>
