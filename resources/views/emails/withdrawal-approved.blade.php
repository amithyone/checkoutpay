<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Approved - {{ $appName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f5f7fa; line-height: 1.6; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .email-header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .email-header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin-bottom: 10px; }
        .email-body { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: 600; color: #1a202c; margin-bottom: 20px; }
        .content-text { font-size: 15px; color: #4a5568; margin-bottom: 20px; line-height: 1.7; }
        .success-box { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border: 2px solid #10b981; border-radius: 12px; padding: 30px; margin: 30px 0; text-align: center; }
        .success-box .icon { width: 64px; height: 64px; background: #10b981; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #ffffff; }
        .success-box .amount { font-size: 36px; font-weight: 700; color: #065f46; margin-bottom: 10px; }
        .info-grid { display: grid; gap: 15px; margin: 25px 0; }
        .info-item { background-color: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #10b981; }
        .info-item .label { font-size: 12px; color: #718096; text-transform: uppercase; margin-bottom: 5px; }
        .info-item .value { font-size: 16px; font-weight: 600; color: #1a202c; }
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
                @php
                    $emailLogo = \App\Models\Setting::get('email_logo');
                    $emailLogoPath = $emailLogo ? storage_path('app/public/' . $emailLogo) : null;
                @endphp
                @if($emailLogo && $emailLogoPath && file_exists($emailLogoPath))
                    <img src="{{ asset('storage/' . $emailLogo) }}" alt="{{ $appName }}" style="max-height: 50px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto;">
                @else
                    <h1>{{ $appName }}</h1>
                @endif
                <div style="color: rgba(255, 255, 255, 0.9); font-size: 14px;">Withdrawal Approved</div>
            </div>
            <div class="email-body">
                <div class="greeting">Hello {{ $business->name }}!</div>
                <div class="content-text">Great news! Your withdrawal request has been approved and the funds are being processed.</div>
                <div class="success-box">
                    <div class="icon">✓</div>
                    <div class="amount">₦{{ number_format($withdrawal->amount, 2) }}</div>
                    <div style="color: #065f46; font-size: 14px; margin-top: 10px;">Status: Approved</div>
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
                        <div class="label">Processed Date</div>
                        <div class="value">{{ $withdrawal->processed_at ? $withdrawal->processed_at->format('F d, Y \a\t g:i A') : 'N/A' }}</div>
                    </div>
                </div>
                <div class="content-text">The funds should reflect in your account within 1-2 business days. If you don't receive the funds, please contact our support team.</div>
                <div style="text-align: center;">
                    <a href="{{ route('business.withdrawals.show', $withdrawal) }}" style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #ffffff !important; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 20px 0;">View Details</a>
                </div>
            </div>
            <div class="email-footer">
                <div class="footer-text">© {{ date('Y') }} {{ $appName }}. All rights reserved.</div>
            </div>
        </div>
    </div>
</body>
</html>
