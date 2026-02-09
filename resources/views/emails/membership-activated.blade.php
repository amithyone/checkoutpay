<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Activated</title>
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
        .info-box { background-color: #f7fafc; border-left: 4px solid #10b981; padding: 15px; margin: 15px 0; border-radius: 8px; }
        .info-box .label { font-size: 12px; color: #718096; text-transform: uppercase; margin-bottom: 5px; }
        .info-box .value { font-size: 16px; font-weight: 600; color: #1a202c; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, #3C50E0 0%, #2E40C7 100%); color: #ffffff !important; text-decoration: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; margin: 20px 0; box-shadow: 0 4px 12px rgba(60, 80, 224, 0.4); }
        .email-footer { background-color: #1a202c; padding: 30px; text-align: center; }
        .email-footer .footer-text { color: #a0aec0; font-size: 13px; }
        .success-icon { text-align: center; margin: 20px 0; }
        .success-icon i { font-size: 64px; color: #10b981; }
    </style>
</head>
<body>
    <div style="padding: 20px;">
        <div class="email-container">
            <div class="email-header">
                <h1>🎉 Membership Activated!</h1>
                <div class="subtitle">Welcome to {{ $subscription->membership->name }}</div>
            </div>
            <div class="email-body">
                <div class="greeting">Hello {{ $subscription->member_name }}!</div>
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="content-text">
                    Great news! Your membership to <strong>{{ $subscription->membership->name }}</strong> has been successfully activated. Your membership card has been attached to this email.
                </div>
                <div class="info-box">
                    <div class="label">Subscription Number</div>
                    <div class="value">{{ $subscription->subscription_number }}</div>
                </div>
                <div class="info-box">
                    <div class="label">Membership</div>
                    <div class="value">{{ $subscription->membership->name }}</div>
                </div>
                <div class="info-box">
                    <div class="label">Business</div>
                    <div class="value">{{ $subscription->membership->business->name }}</div>
                </div>
                @if($subscription->membership->category)
                <div class="info-box">
                    <div class="label">Category</div>
                    <div class="value">{{ $subscription->membership->category->name }}</div>
                </div>
                @endif
                <div class="info-box">
                    <div class="label">Start Date</div>
                    <div class="value">{{ $subscription->start_date->format('F d, Y') }}</div>
                </div>
                <div class="info-box">
                    <div class="label">Expires On</div>
                    <div class="value">{{ $subscription->expires_at->format('F d, Y') }}</div>
                </div>
                <div class="content-text" style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 15px; border-radius: 8px; margin-top: 20px;">
                    <strong>📱 Lost your card?</strong><br>
                    You can always find and download your membership card by visiting the membership page and using the "Find My Membership Card" feature with your email or phone number.
                </div>
            </div>
            <div class="email-footer">
                <div class="footer-text">© {{ date('Y') }} {{ \App\Models\Setting::get('site_name', 'CheckoutPay') }}. All rights reserved.</div>
            </div>
        </div>
    </div>
</body>
</html>
